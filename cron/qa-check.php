
<?php
/** QA v2 */
$baseUrl='https://toutvamal.fr';
$errors=[];$warnings=[];$success=[];
function ok($h){return is_array($h)&&isset($h[0])&&strpos($h[0],'200')!==false;}
echo "=== QA Check ToutVaMal.fr (v2) ===

";
// homepage
$homepage=@file_get_contents($baseUrl.'/');
if($homepage===false){$errors[]='Homepage not accessible';}
else{
 if(strpos($homepage,'class="nav"')!==false){$success[]='Homepage: Navigation present';}else{$errors[]='Homepage: Navigation missing';}
 if(strpos($homepage,'class="footer"')!==false){$success[]='Homepage: Footer present';}else{$errors[]='Homepage: Footer missing';}
 if(strpos($homepage,'<title>')!==false){$success[]='Homepage: Title present';}else{$errors[]='Homepage: Title missing';}
}
// css
$cssHeaders=@get_headers($baseUrl.'/css/style.css');
if(ok($cssHeaders)){$css=@file_get_contents($baseUrl.'/css/style.css'); if(strlen((string)$css)>5000){$success[]='CSS: Loaded and non-trivial';}else{$warnings[]='CSS: Loaded but unusually small';}}
else{$errors[]='CSS: Not accessible';}
// article
$articleUrl=null;
if(preg_match('#href="(/articles/[^"]+\.html)"#',$homepage,$m)){$articleUrl=$baseUrl.$m[1];}
if(!$articleUrl){$errors[]='Article: Could not discover article URL from homepage';}
else{
 $article=@file_get_contents($articleUrl);
 if($article===false){$errors[]='Article: Page not accessible';}
 else{
  if(strpos($article,'article-content')!==false || strpos($article,'<article')!==false){$success[]='Article: Content present';}else{$warnings[]='Article: Content marker not found';}
  if(strpos($article,'og:title')!==false){$success[]='Article: OG tags present';}else{$warnings[]='Article: OG tags missing';}
  if(strpos($article,'application/ld+json')!==false){$success[]='Article: JSON-LD present';}else{$warnings[]='Article: JSON-LD missing';}
  if(preg_match('#<img[^>]+src="([^"]+)"#i',$article,$im)){
    $img=$im[1]; if(strpos($img,'http')!==0){$img=$baseUrl.'/'.ltrim($img,'/');}
    $ih=@get_headers($img); if(ok($ih)){$success[]='Article: Main image accessible';}else{$warnings[]='Article: Main image not accessible';}
  }
 }
}
// endpoints
foreach(['/robots.txt'=>'robots','/sitemap.xml'=>'sitemap','/favicon.ico'=>'favicon','/admin/index.html'=>'admin'] as $p=>$n){$h=@get_headers($baseUrl.$p); if(ok($h)){$success[]="Endpoint: $n accessible";}else{$warnings[]="Endpoint: $n not accessible";}}
$apiH=@get_headers($baseUrl.'/api/v2/stats.php');
if(is_array($apiH)&&isset($apiH[0])){ if(strpos($apiH[0],'401')!==false || strpos($apiH[0],'200')!==false){$success[]='API: stats endpoint reachable';}else{$warnings[]='API: unexpected response on stats endpoint';}}
else{$errors[]='API: stats endpoint unreachable';}
// summary
echo "
=== RESULTS ===

";
if($errors){echo "❌ ERRORS (".count($errors)."):
"; foreach($errors as $e) echo "   - $e
"; echo "
";}
if($warnings){echo "⚠️  WARNINGS (".count($warnings)."):
"; foreach($warnings as $w) echo "   - $w
"; echo "
";}
if($success){echo "✅ SUCCESS (".count($success)."):
"; foreach($success as $s) echo "   - $s
"; echo "
";}
$status=empty($errors)?(empty($warnings)?'PASS':'PASS WITH WARNINGS'):'FAIL';
echo "=== STATUS: $status ===
";
exit(empty($errors)?0:1);
