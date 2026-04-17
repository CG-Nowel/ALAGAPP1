<?php

declare(strict_types=1);

namespace App\Helpers;

class Pagination
{
    private int $currentPage;
    private int $perPage;
    private int $total;
    private int $totalPages;

    public function __construct(int $total, int $perPage = 15, ?int $currentPage = null)
    {
        $this->total = $total;
        $this->perPage = max(1, $perPage);
        $this->totalPages = (int) ceil($this->total / $this->perPage);
        $this->currentPage = $this->sanitizePage($currentPage ?? (int) ($_GET['page'] ?? 1));
    }

    private function sanitizePage(int $page): int
    {
        return max(1, min($page, max(1, $this->totalPages)));
    }

    public function getOffset(): int
    {
        return ($this->currentPage - 1) * $this->perPage;
    }

    public function getLimit(): int
    {
        return $this->perPage;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function hasMore(): bool
    {
        return $this->currentPage < $this->totalPages;
    }

    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'total_pages' => $this->totalPages,
            'has_more' => $this->hasMore(),
        ];
    }

    /**
     * Render HTML pagination controls.
     */
    public function render(string $baseUrl = '?'): string
    {
        if ($this->totalPages <= 1) {
            return '';
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        $html = '<nav aria-label="Pagination"><ul class="pagination">';

        // Previous button
        if ($this->currentPage > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . ($this->currentPage - 1) . '">&laquo; Previous</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">&laquo; Previous</span></li>';
        }

        // Page numbers
        $startPage = max(1, $this->currentPage - 2);
        $endPage = min($this->totalPages, $this->currentPage + 2);

        if ($startPage > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=1">1</a></li>';
            if ($startPage > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            if ($i === $this->currentPage) {
                $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . $i . '">' . $i . '</a></li>';
            }
        }

        if ($endPage < $this->totalPages) {
            if ($endPage < $this->totalPages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . $this->totalPages . '">' . $this->totalPages . '</a></li>';
        }

        // Next button
        if ($this->currentPage < $this->totalPages) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . ($this->currentPage + 1) . '">Next &raquo;</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">Next &raquo;</span></li>';
        }

        $html .= '</ul></nav>';

        return $html;
    }
}
