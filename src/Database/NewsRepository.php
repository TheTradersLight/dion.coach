<?php
declare(strict_types=1);

namespace App\Database;

final class NewsRepository
{
    public static function latest(int $limit = 3): array
    {
        $limit = max(1, (int)$limit);

        return Database::fetchAll(
            "SELECT id, slug, title, excerpt, published_at, is_pinned
             FROM news
             WHERE is_published = 1
             ORDER BY is_pinned DESC, published_at DESC
             LIMIT {$limit}"
        );
    }

    public static function listPublished(int $limit, int $offset): array
    {
        $limit  = max(1, (int)$limit);
        $offset = max(0, (int)$offset);

        return Database::fetchAll(
            "SELECT id, slug, title, excerpt, published_at, is_pinned
             FROM news
             WHERE is_published = 1
             ORDER BY is_pinned DESC, published_at DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
    }

    public static function countPublished(): int
    {
        $row = Database::fetch(
            "SELECT COUNT(*) AS c
             FROM news
             WHERE is_published = 1"
        );

        return (int)($row['c'] ?? 0);
    }

    public static function findPublishedBySlug(string $slug): ?array
    {
        $row = Database::fetch(
            "SELECT id, slug, title, excerpt, body_html, image_path, meta_description, published_at, is_pinned
             FROM news
             WHERE slug = ?
               AND is_published = 1
             LIMIT 1",
            [$slug]
        );

        return $row ?: null;
    }

    public static function upsert(array $data): void
    {
        Database::execute(
            "INSERT INTO news
                (slug, title, excerpt, body_html, image_path, meta_description, published_at, is_published, is_pinned)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                excerpt = VALUES(excerpt),
                body_html = VALUES(body_html),
                image_path = VALUES(image_path),
                meta_description = VALUES(meta_description),
                published_at = VALUES(published_at),
                is_published = VALUES(is_published),
                is_pinned = VALUES(is_pinned)",
            [
                (string)($data['slug'] ?? ''),
                (string)($data['title'] ?? ''),
                $data['excerpt'] ?? null,
                (string)($data['body_html'] ?? ''),
                $data['image_path'] ?? null,
                $data['meta_description'] ?? null,
                $data['published_at'] ?? null,
                (int)($data['is_published'] ?? 0),
                (int)($data['is_pinned'] ?? 0),
            ]
        );
    }

    public static function findAll(): array
    {
        return Database::fetchAll(
            "SELECT id, slug, title, image_path, is_published, is_pinned, published_at, created_at
             FROM news
             ORDER BY is_pinned DESC, created_at DESC"
        );
    }

    public static function delete(int $id): void
    {
        Database::execute("DELETE FROM news WHERE id = ?", [$id]);
    }
}
