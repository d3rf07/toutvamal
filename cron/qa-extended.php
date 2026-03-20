<?php
$base = 'https://toutvamal.fr';
$assertions = 0; $fails = 0; $warn = 0;
$results = [];
function assertTrue($cond, $msg, &$assertions, &$fails, &$results) {
    $assertions++;
    if (!$cond) { $fails++; $results[] = "FAIL: $msg"; }
}
function warnIf($cond, $msg, &$warn, &$results) {
    if (!$cond) { $warn++; $results[] = "WARN: $msg"; }
}
function getUrl($url) {
    $ctx = stream_context_create(['http'=>['timeout'=>12,'ignore_errors'=>true],'ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]);
    $body = @file_get_contents($url,false,$ctx);
    $headers = $http_response_header ?? [];
    $status = 0;
    if (isset($headers[0]) && preg_match('/\s(\d{3})\s/', $headers[0], $m)) $status=(int)$m[1];
    return [$status, $headers, (string)$body];
}

[$st,$h,$xml] = getUrl($base.'/sitemap.xml');
assertTrue($st===200, 'sitemap.xml reachable', $assertions, $fails, $results);
$urls=[];
if ($xml) {
    preg_match_all('#<loc>([^<]+)</loc>#', $xml, $m);
    $urls = array_values(array_unique($m[1]));
}
assertTrue(count($urls) > 20, 'sitemap contains >20 urls', $assertions, $fails, $results);
$urls = array_slice($urls, 0, 140);

foreach ($urls as $u) {
    [$code,$headers,$html] = getUrl($u);
    assertTrue($code===200, "URL 200 $u", $assertions, $fails, $results);
    assertTrue(stripos($html,'<title>')!==false, "title present $u", $assertions, $fails, $results);
    assertTrue((bool)preg_match('/<meta[^>]+name=["\']description["\']/i',$html), "meta description $u", $assertions, $fails, $results);
    assertTrue((bool)preg_match('/<link[^>]+rel=["\']canonical["\']/i',$html), "canonical $u", $assertions, $fails, $results);
    assertTrue((bool)preg_match('/<meta[^>]+property=["\']og:title["\']/i',$html), "og:title $u", $assertions, $fails, $results);
    assertTrue((bool)preg_match('/<meta[^>]+property=["\']og:description["\']/i',$html), "og:description $u", $assertions, $fails, $results);
    assertTrue((bool)preg_match('/<meta[^>]+property=["\']og:image["\']/i',$html), "og:image $u", $assertions, $fails, $results);
    assertTrue((bool)preg_match('/<meta[^>]+name=["\']twitter:card["\']/i',$html), "twitter:card $u", $assertions, $fails, $results);
    assertTrue(stripos($html,'application/ld+json')!==false, "json-ld $u", $assertions, $fails, $results);
    $joined = strtolower(implode("\n", $headers));
    warnIf(strpos($joined, 'cache-control')!==false, "cache-control missing $u", $warn, $results);
}

[$code,$headers,$home] = getUrl($base.'/');
$joined = strtolower(implode("\n", $headers));
assertTrue(strpos($joined,'strict-transport-security')!==false, 'HSTS homepage', $assertions, $fails, $results);
assertTrue(strpos($joined,'x-frame-options')!==false, 'X-Frame-Options homepage', $assertions, $fails, $results);
assertTrue(strpos($joined,'x-content-type-options')!==false, 'X-Content-Type-Options homepage', $assertions, $fails, $results);
warnIf(strpos($joined,'content-security-policy')!==false, 'CSP missing homepage', $warn, $results);


// Additional baseline assertions (global)
[$rbCode,$rbHeaders,$rbBody] = getUrl($base.'/robots.txt');
assertTrue($rbCode===200, 'robots.txt reachable', $assertions, $fails, $results);
assertTrue(stripos($rbBody,'Sitemap:')!==false, 'robots has sitemap directive', $assertions, $fails, $results);
[$smCode,$smHeaders,$smBody] = getUrl($base.'/sitemap.xml');
assertTrue($smCode===200, 'sitemap.xml reachable (global)', $assertions, $fails, $results);
assertTrue(substr_count($smBody,'<url>')>50, 'sitemap has >50 url entries', $assertions, $fails, $results);
[$admCode] = getUrl($base.'/admin/index.html');
assertTrue($admCode===200, 'admin page reachable', $assertions, $fails, $results);
[$apiCode] = getUrl($base.'/api/v2/stats.php');
assertTrue(in_array($apiCode,[200,401]), 'api stats reachable/auth', $assertions, $fails, $results);

$passed = $assertions - $fails;
echo "Extended QA completed\n";
echo "Assertions: $assertions\nPassed: $passed\nFailed: $fails\nWarnings: $warn\n";
@file_put_contents(__DIR__.'/../logs/qa-extended-'.date('Ymd_His').'.log', implode("\n", $results));
exit($fails===0 ? 0 : 1);
