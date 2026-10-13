<?php

declare(strict_types=1);

namespace App\Core;

final class Paginator
{
    /**
     * @param string $from   trecho "FROM … WHERE …" (sem ORDER/LIMIT)
     * @return array{items: array, total: int, page: int, pages: int, per_page: int}
     */
    public static function paginate(string $select, string $from, array $params, string $orderBy, int $page, int $perPage = 15): array
    {
        $total = (int) Database::value("SELECT COUNT(*) {$from}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;
        $items = Database::fetchAll("SELECT {$select} {$from} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}", $params);
        return compact('items', 'total', 'page', 'pages') + ['per_page' => $perPage];
    }
}
