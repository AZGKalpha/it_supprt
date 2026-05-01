<?php
function renderPagination($currentPage, $totalPages, $extraParams = []) {
    if ($totalPages <= 1) return '';

    $html = '<div class="pagination">';
    $baseUrl = strtok($_SERVER['REQUEST_URI'], '?');

    $buildUrl = function($page) use ($baseUrl, $extraParams) {
        $params = $extraParams;
        if ($page > 1) {
            $params['page'] = $page;
        }
        $params = array_filter($params, function($v) { return $v !== '' && $v !== null; });
        return $baseUrl . '?' . http_build_query($params);
    };

    // назад
    if ($currentPage > 1) {
        $html .= '<a href="' . htmlspecialchars($buildUrl($currentPage - 1)) . '">← Назад</a>';
    }

    $start = max(1, $currentPage - 1);
    $end = min($totalPages, $currentPage + 1);

    // первая
    if ($start > 1) {
        $html .= '<a href="' . htmlspecialchars($buildUrl(1)) . '">1</a>';
        if ($start > 2) $html .= '<span>...</span>';
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i == $currentPage) {
            $html .= '<span class="active">' . $i . '</span>';
        } else {
            $html .= '<a href="' . htmlspecialchars($buildUrl($i)) . '">' . $i . '</a>';
        }
    }

    // последняя
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $html .= '<span>...</span>';
        $html .= '<a href="' . htmlspecialchars($buildUrl($totalPages)) . '">' . $totalPages . '</a>';
    }

    // далее
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . htmlspecialchars($buildUrl($currentPage + 1)) . '">Далее →</a>';
    }

    return $html . '</div>';
}
?>