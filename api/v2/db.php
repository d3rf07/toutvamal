<?php
/**
 * ToutVaMal.fr - Database Helper v2
 * Singleton PDO avec méthodes utilitaires
 */

require_once dirname(dirname(__DIR__)) . '/config.php';

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            self::$instance = new PDO('sqlite:' . DB_PATH);
            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$instance->exec('PRAGMA foreign_keys = ON');
        }
        return self::$instance;
    }

    // ========== ARTICLES ==========

    public static function getArticles(int $limit = 50, int $offset = 0, ?string $category = null, ?string $status = null): array {
        $db = self::getInstance();
        $where = ['1=1'];
        $params = [];

        if ($category) {
            $where[] = 'category = :category';
            $params[':category'] = $category;
        }
        if ($status) {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }

        $whereClause = implode(' AND ', $where);
        $stmt = $db->prepare("
            SELECT a.*, j.name as journalist_name, j.photo_path as journalist_photo
            FROM articles a
            LEFT JOIN journalists j ON a.journalist_id = j.id
            WHERE $whereClause
            ORDER BY a.published_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getArticleById(int $id): ?array {
        $db = self::getInstance();
        $stmt = $db->prepare("
            SELECT a.*, j.name as journalist_name, j.photo_path as journalist_photo
            FROM articles a
            LEFT JOIN journalists j ON a.journalist_id = j.id
            WHERE a.id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function getArticleBySlug(string $slug): ?array {
        $db = self::getInstance();
        $stmt = $db->prepare("
            SELECT a.*, j.name as journalist_name, j.photo_path as journalist_photo
            FROM articles a
            LEFT JOIN journalists j ON a.journalist_id = j.id
            WHERE a.slug = :slug
        ");
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetch() ?: null;
    }

    public static function createArticle(array $data): int {
        $db = self::getInstance();
        $stmt = $db->prepare("
            INSERT INTO articles (slug, title, content, excerpt, category, image_path, journalist_id,
                source_title, source_url, published_at, status, meta_title, meta_description,
                og_image, schema_type, canonical_url, robots)
            VALUES (:slug, :title, :content, :excerpt, :category, :image_path, :journalist_id,
                :source_title, :source_url, :published_at, :status, :meta_title, :meta_description,
                :og_image, :schema_type, :canonical_url, :robots)
        ");
        $stmt->execute([
            ':slug' => $data['slug'],
            ':title' => $data['title'],
            ':content' => $data['content'] ?? '',
            ':excerpt' => $data['excerpt'] ?? '',
            ':category' => $data['category'] ?? 'chaos-politique',
            ':image_path' => $data['image_path'] ?? null,
            ':journalist_id' => $data['journalist_id'] ?? null,
            ':source_title' => $data['source_title'] ?? null,
            ':source_url' => $data['source_url'] ?? null,
            ':published_at' => $data['published_at'] ?? date('Y-m-d H:i:s'),
            ':status' => $data['status'] ?? 'draft',
            ':meta_title' => $data['meta_title'] ?? null,
            ':meta_description' => $data['meta_description'] ?? null,
            ':og_image' => $data['og_image'] ?? null,
            ':schema_type' => $data['schema_type'] ?? 'NewsArticle',
            ':canonical_url' => $data['canonical_url'] ?? null,
            ':robots' => $data['robots'] ?? 'index,follow'
        ]);
        return (int)$db->lastInsertId();
    }

    public static function updateArticle(int $id, array $data): bool {
        $db = self::getInstance();
        $fields = [];
        $params = [':id' => $id];

        $allowedFields = ['slug', 'title', 'content', 'excerpt', 'category', 'image_path',
            'journalist_id', 'source_title', 'source_url', 'published_at', 'status',
            'meta_title', 'meta_description', 'og_image', 'schema_type', 'canonical_url', 'robots'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $fields[] = "updated_at = datetime('now')";
        $fieldClause = implode(', ', $fields);

        $stmt = $db->prepare("UPDATE articles SET $fieldClause WHERE id = :id");
        return $stmt->execute($params);
    }

    public static function deleteArticle(int $id): bool {
        $db = self::getInstance();
        $stmt = $db->prepare("DELETE FROM articles WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public static function countArticles(?string $category = null, ?string $status = null): int {
        $db = self::getInstance();
        $where = ['1=1'];
        $params = [];

        if ($category) {
            $where[] = 'category = :category';
            $params[':category'] = $category;
        }
        if ($status) {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }

        $whereClause = implode(' AND ', $where);
        $stmt = $db->prepare("SELECT COUNT(*) FROM articles WHERE $whereClause");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ========== JOURNALISTS ==========

    public static function getJournalists(bool $activeOnly = false): array {
        $db = self::getInstance();
        $where = $activeOnly ? 'WHERE active = 1' : '';
        $stmt = $db->query("SELECT * FROM journalists $where ORDER BY name");
        return $stmt->fetchAll();
    }

    public static function getJournalistById(int $id): ?array {
        $db = self::getInstance();
        $stmt = $db->prepare("SELECT * FROM journalists WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function getRandomJournalist(): ?array {
        $db = self::getInstance();
        $stmt = $db->query("SELECT * FROM journalists WHERE active = 1 ORDER BY RANDOM() LIMIT 1");
        return $stmt->fetch() ?: null;
    }

    public static function createJournalist(array $data): int {
        $db = self::getInstance();
        $stmt = $db->prepare("
            INSERT INTO journalists (slug, name, role, style, bio, photo_path, badge, mood, active)
            VALUES (:slug, :name, :role, :style, :bio, :photo_path, :badge, :mood, :active)
        ");
        $stmt->execute([
            ':slug' => $data['slug'] ?? slugify($data['name']),
            ':name' => $data['name'],
            ':role' => $data['role'] ?? 'Journaliste',
            ':style' => $data['style'] ?? '',
            ':bio' => $data['bio'] ?? '',
            ':photo_path' => $data['photo_path'] ?? null,
            ':badge' => $data['badge'] ?? null,
            ':mood' => $data['mood'] ?? null,
            ':active' => $data['active'] ?? 1
        ]);
        return (int)$db->lastInsertId();
    }

    public static function updateJournalist(int $id, array $data): bool {
        $db = self::getInstance();
        $fields = [];
        $params = [':id' => $id];

        $allowedFields = ['slug', 'name', 'role', 'style', 'bio', 'photo_path', 'badge', 'mood', 'active'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $fieldClause = implode(', ', $fields);
        $stmt = $db->prepare("UPDATE journalists SET $fieldClause WHERE id = :id");
        return $stmt->execute($params);
    }

    public static function deleteJournalist(int $id): bool {
        $db = self::getInstance();
        $stmt = $db->prepare("DELETE FROM journalists WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ========== CONFIG ==========

    public static function getConfig(string $key, $default = null) {
        $db = self::getInstance();
        $stmt = $db->prepare("SELECT value FROM config WHERE key = :key");
        $stmt->execute([':key' => $key]);
        $result = $stmt->fetchColumn();
        if ($result === false) return $default;
        $decoded = json_decode($result, true);
        return $decoded !== null ? $decoded : $result;
    }

    public static function setConfig(string $key, $value): bool {
        $db = self::getInstance();
        $jsonValue = is_array($value) || is_object($value) ? json_encode($value) : $value;
        $stmt = $db->prepare("
            INSERT OR REPLACE INTO config (key, value, updated_at)
            VALUES (:key, :value, datetime('now'))
        ");
        return $stmt->execute([':key' => $key, ':value' => $jsonValue]);
    }

    public static function getAllConfig(): array {
        $db = self::getInstance();
        $stmt = $db->query("SELECT key, value FROM config ORDER BY key");
        $result = [];
        while ($row = $stmt->fetch()) {
            $decoded = json_decode($row['value'], true);
            $result[$row['key']] = $decoded !== null ? $decoded : $row['value'];
        }
        return $result;
    }

    // ========== RSS SOURCES ==========

    public static function getRssSources(bool $activeOnly = false): array {
        $db = self::getInstance();
        $where = $activeOnly ? 'WHERE active = 1' : '';
        $stmt = $db->query("SELECT * FROM rss_sources $where ORDER BY name");
        return $stmt->fetchAll();
    }

    public static function createRssSource(array $data): int {
        $db = self::getInstance();
        $stmt = $db->prepare("
            INSERT INTO rss_sources (name, url, category, active)
            VALUES (:name, :url, :category, :active)
        ");
        $stmt->execute([
            ':name' => $data['name'],
            ':url' => $data['url'],
            ':category' => $data['category'] ?? null,
            ':active' => $data['active'] ?? 1
        ]);
        return (int)$db->lastInsertId();
    }

    public static function updateRssSource(int $id, array $data): bool {
        $db = self::getInstance();
        $fields = [];
        $params = [':id' => $id];

        foreach (['name', 'url', 'category', 'active', 'last_fetch'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $fieldClause = implode(', ', $fields);
        $stmt = $db->prepare("UPDATE rss_sources SET $fieldClause WHERE id = :id");
        return $stmt->execute($params);
    }

    public static function deleteRssSource(int $id): bool {
        $db = self::getInstance();
        $stmt = $db->prepare("DELETE FROM rss_sources WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ========== GENERATION LOGS ==========

    public static function createGenerationLog(array $data): int {
        $db = self::getInstance();
        $stmt = $db->prepare("
            INSERT INTO generation_logs (source_url, source_title, article_id, journalist_id,
                status, error_message, model_used, tokens_used, cost_estimate, generation_time)
            VALUES (:source_url, :source_title, :article_id, :journalist_id,
                :status, :error_message, :model_used, :tokens_used, :cost_estimate, :generation_time)
        ");
        $stmt->execute([
            ':source_url' => $data['source_url'] ?? null,
            ':source_title' => $data['source_title'] ?? null,
            ':article_id' => $data['article_id'] ?? null,
            ':journalist_id' => $data['journalist_id'] ?? null,
            ':status' => $data['status'] ?? 'pending',
            ':error_message' => $data['error_message'] ?? null,
            ':model_used' => $data['model_used'] ?? null,
            ':tokens_used' => $data['tokens_used'] ?? null,
            ':cost_estimate' => $data['cost_estimate'] ?? null,
            ':generation_time' => $data['generation_time'] ?? null
        ]);
        return (int)$db->lastInsertId();
    }

    public static function updateGenerationLog(int $id, array $data): bool {
        $db = self::getInstance();
        $fields = [];
        $params = [':id' => $id];

        foreach (['article_id', 'status', 'error_message', 'model_used', 'tokens_used', 'cost_estimate', 'generation_time'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $fieldClause = implode(', ', $fields);
        $stmt = $db->prepare("UPDATE generation_logs SET $fieldClause WHERE id = :id");
        return $stmt->execute($params);
    }

    public static function getGenerationLogs(int $limit = 100, int $offset = 0, ?string $status = null): array {
        $db = self::getInstance();
        $where = $status ? 'WHERE status = :status' : '';
        $stmt = $db->prepare("
            SELECT gl.*, a.title as article_title, j.name as journalist_name
            FROM generation_logs gl
            LEFT JOIN articles a ON gl.article_id = a.id
            LEFT JOIN journalists j ON gl.journalist_id = j.id
            $where
            ORDER BY gl.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        if ($status) $stmt->bindValue(':status', $status);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ========== NEWSLETTER ==========

    public static function getNewsletterSubscribers(bool $confirmedOnly = true): array {
        $db = self::getInstance();
        $where = $confirmedOnly ? 'WHERE confirmed_at IS NOT NULL AND unsubscribed_at IS NULL' : '';
        $stmt = $db->query("SELECT * FROM newsletter $where ORDER BY subscribed_at DESC");
        return $stmt->fetchAll();
    }

    public static function subscribeNewsletter(string $email, string $source = 'website'): array {
        $db = self::getInstance();

        // Check existing
        $stmt = $db->prepare("SELECT * FROM newsletter WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ($existing['unsubscribed_at']) {
                // Resubscribe
                $stmt = $db->prepare("UPDATE newsletter SET unsubscribed_at = NULL, subscribed_at = datetime('now') WHERE id = :id");
                $stmt->execute([':id' => $existing['id']]);
                return ['status' => 'resubscribed', 'id' => $existing['id']];
            }
            return ['status' => 'already_subscribed', 'id' => $existing['id']];
        }

        $token = bin2hex(random_bytes(32));
        $stmt = $db->prepare("
            INSERT INTO newsletter (email, source, confirmation_token)
            VALUES (:email, :source, :token)
        ");
        $stmt->execute([':email' => $email, ':source' => $source, ':token' => $token]);

        return ['status' => 'subscribed', 'id' => (int)$db->lastInsertId(), 'token' => $token];
    }

    public static function confirmNewsletter(string $token): bool {
        $db = self::getInstance();
        $stmt = $db->prepare("
            UPDATE newsletter SET confirmed_at = datetime('now')
            WHERE confirmation_token = :token AND confirmed_at IS NULL
        ");
        $stmt->execute([':token' => $token]);
        return $stmt->rowCount() > 0;
    }

    public static function unsubscribeNewsletter(string $email): bool {
        $db = self::getInstance();
        $stmt = $db->prepare("UPDATE newsletter SET unsubscribed_at = datetime('now') WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return $stmt->rowCount() > 0;
    }

    // ========== SEO ANALYTICS ==========

    public static function logSeoData(array $data): int {
        $db = self::getInstance();
        $stmt = $db->prepare("
            INSERT INTO seo_analytics (article_id, page_url, impressions, clicks, ctr, position, query, date_recorded)
            VALUES (:article_id, :page_url, :impressions, :clicks, :ctr, :position, :query, :date_recorded)
        ");
        $stmt->execute([
            ':article_id' => $data['article_id'] ?? null,
            ':page_url' => $data['page_url'],
            ':impressions' => $data['impressions'] ?? 0,
            ':clicks' => $data['clicks'] ?? 0,
            ':ctr' => $data['ctr'] ?? 0,
            ':position' => $data['position'] ?? 0,
            ':query' => $data['query'] ?? null,
            ':date_recorded' => $data['date_recorded'] ?? date('Y-m-d')
        ]);
        return (int)$db->lastInsertId();
    }

    public static function getSeoStats(int $days = 30): array {
        $db = self::getInstance();
        $stmt = $db->prepare("
            SELECT
                SUM(impressions) as total_impressions,
                SUM(clicks) as total_clicks,
                AVG(ctr) as avg_ctr,
                AVG(position) as avg_position
            FROM seo_analytics
            WHERE date_recorded >= date('now', '-' || :days || ' days')
        ");
        $stmt->execute([':days' => $days]);
        return $stmt->fetch() ?: [];
    }

    // ========== STATS ==========

    public static function getDashboardStats(): array {
        $db = self::getInstance();

        return [
            'articles' => [
                'total' => self::countArticles(),
                'published' => self::countArticles(null, 'published'),
                'draft' => self::countArticles(null, 'draft'),
                'by_category' => self::getArticlesByCategory()
            ],
            'journalists' => [
                'total' => (int)$db->query("SELECT COUNT(*) FROM journalists")->fetchColumn(),
                'active' => (int)$db->query("SELECT COUNT(*) FROM journalists WHERE active = 1")->fetchColumn()
            ],
            'newsletter' => [
                'total' => (int)$db->query("SELECT COUNT(*) FROM newsletter")->fetchColumn(),
                'confirmed' => (int)$db->query("SELECT COUNT(*) FROM newsletter WHERE confirmed_at IS NOT NULL")->fetchColumn(),
                'unsubscribed' => (int)$db->query("SELECT COUNT(*) FROM newsletter WHERE unsubscribed_at IS NOT NULL")->fetchColumn()
            ],
            'generations' => [
                'total' => (int)$db->query("SELECT COUNT(*) FROM generation_logs")->fetchColumn(),
                'success' => (int)$db->query("SELECT COUNT(*) FROM generation_logs WHERE status = 'success'")->fetchColumn(),
                'failed' => (int)$db->query("SELECT COUNT(*) FROM generation_logs WHERE status = 'error'")->fetchColumn(),
                'today' => (int)$db->query("SELECT COUNT(*) FROM generation_logs WHERE date(created_at) = date('now')")->fetchColumn()
            ]
        ];
    }

    private static function getArticlesByCategory(): array {
        $db = self::getInstance();
        $stmt = $db->query("SELECT category, COUNT(*) as count FROM articles GROUP BY category");
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[$row['category']] = (int)$row['count'];
        }
        return $result;
    }
}
