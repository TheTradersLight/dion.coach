<?php
declare(strict_types=1);

namespace App\Database;

final class SkillRepository
{
    public static function getCategoriesWithSkills(int $campId): array
    {
        $categories = Database::fetchAll(
            "SELECT * FROM skill_categories WHERE camp_id = ? ORDER BY sort_order ASC, id ASC",
            [$campId]
        );

        $skills = Database::fetchAll(
            "SELECT s.* FROM skills s
             JOIN skill_categories sc ON s.category_id = sc.id
             WHERE sc.camp_id = ?
             ORDER BY s.sort_order ASC, s.id ASC",
            [$campId]
        );

        // Index skills by category_id
        $skillsByCategory = [];
        foreach ($skills as $skill) {
            $skillsByCategory[$skill['category_id']][] = $skill;
        }

        // Build tree: level 1 categories with children and skills
        $tree = [];
        $catById = [];
        foreach ($categories as $cat) {
            $cat['skills'] = $skillsByCategory[$cat['id']] ?? [];
            $cat['children'] = [];
            $catById[$cat['id']] = $cat;
        }

        foreach ($catById as $id => $cat) {
            if (!empty($cat['parent_id']) && isset($catById[$cat['parent_id']])) {
                $catById[$cat['parent_id']]['children'][] = &$catById[$id];
            } else {
                $tree[] = &$catById[$id];
            }
        }
        unset($catById);

        return $tree;
    }

    public static function findCategoryById(int $id): ?array
    {
        $row = Database::fetch("SELECT * FROM skill_categories WHERE id = ?", [$id]);
        return $row ?: null;
    }

    public static function createCategory(array $data): int
    {
        $nextOrder = Database::fetch(
            "SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order
             FROM skill_categories WHERE camp_id = ? AND parent_id " . (empty($data['parent_id']) ? "IS NULL" : "= " . (int)$data['parent_id']),
            [(int)$data['camp_id']]
        );

        Database::execute(
            "INSERT INTO skill_categories (camp_id, parent_id, name, sort_order) VALUES (?, ?, ?, ?)",
            [
                (int)$data['camp_id'],
                !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
                (string)($data['name'] ?? ''),
                (int)($nextOrder['next_order'] ?? 1),
            ]
        );
        return (int)Database::lastId();
    }

    public static function updateCategory(int $id, array $data): void
    {
        Database::execute(
            "UPDATE skill_categories SET name = ? WHERE id = ?",
            [(string)($data['name'] ?? ''), $id]
        );
    }

    public static function deleteCategory(int $id): void
    {
        Database::execute("DELETE FROM skill_categories WHERE id = ?", [$id]);
    }

    public static function findSkillById(int $id): ?array
    {
        $row = Database::fetch("SELECT * FROM skills WHERE id = ?", [$id]);
        return $row ?: null;
    }

    public static function createSkill(array $data): int
    {
        $nextOrder = Database::fetch(
            "SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order FROM skills WHERE category_id = ?",
            [(int)$data['category_id']]
        );

        Database::execute(
            "INSERT INTO skills (category_id, name, description, sort_order) VALUES (?, ?, ?, ?)",
            [
                (int)$data['category_id'],
                (string)($data['name'] ?? ''),
                $data['description'] ?? null,
                (int)($nextOrder['next_order'] ?? 1),
            ]
        );
        return (int)Database::lastId();
    }

    public static function updateSkill(int $id, array $data): void
    {
        Database::execute(
            "UPDATE skills SET name = ?, description = ? WHERE id = ?",
            [(string)($data['name'] ?? ''), $data['description'] ?? null, $id]
        );
    }

    public static function deleteSkill(int $id): void
    {
        Database::execute("DELETE FROM skills WHERE id = ?", [$id]);
    }
}
