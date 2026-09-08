<?php

namespace Nawasara\News\Livewire\News\Section;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\News\Jobs\SyncNewsFromPonorogoJob;
use Nawasara\News\Models\Article;
use Nawasara\News\Models\Category;
use Nawasara\News\Models\Setting;

/**
 * One component for the whole page — list, filters, the detail modal, AND
 * settings (post limit + manual sync) — matching the real established
 * convention (a WHM email-accounts page handling list + 6 modals in one
 * component), not the child-component-per-section split the original
 * written conventions doc calls for. Real precedent wins here.
 */
class Table extends Component
{
    use WithPagination;

    public string $search = '';

    /**
     * Array, not scalar — WHM's real, confirmed-working filter-panel usage
     * only ever exercises the multi-select path (its statusFilter is in
     * :multiple). We'd previously built this single-select (scalar, not in
     * :multiple) based on reading filter-panel's docblock rather than a
     * proven example — that's the likely reason nothing was filtering.
     * Matching the proven pattern instead: multi-select, even though 0-1
     * selections is the expected normal case — also lets someone filter by
     * more than one category at once, which is a reasonable bonus, not
     * just a workaround.
     */
    public array $categoryId = [];

    /**
     * Lightweight id, not the model itself — `detail` below is a
     * #[Computed] accessor (matches the real reference's `$this->detail`
     * usage, which only makes sense for a computed/method property, not a
     * plain public one). Keeping just the id here means the full Article
     * model is never serialized into the Livewire wire snapshot; it's
     * loaded fresh server-side only when actually rendering.
     */
    public ?int $detailId = null;

    // Settings state (folded in from the former separate Settings page).
    public int $latestPostLimit = 100;

    public ?string $lastSyncedAt = null;

    public function mount(): void
    {
        $this->refreshSettingsDisplay();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    // updatedCategoryId (past tense), not updatingCategoryId — matching
    // WHM's real working convention (updatedStatusFilter) exactly, having
    // been burned before by deviating from proven patterns on this
    // component. Functionally both fire on change either way; matching
    // removes one more variable while diagnosing.
    public function updatedCategoryId(): void
    {
        $this->resetPage();
    }

    public function openDetail(int $articleId): void
    {
        $this->detailId = $articleId;
        $this->dispatch('modal-open:news-detail');
    }

    #[Computed]
    public function detail(): ?Article
    {
        return $this->detailId
            ? Article::query()->with('category')->find($this->detailId)
            : null;
    }

    public function openSettings(): void
    {
        // Re-pull in case another admin changed it since mount().
        $this->refreshSettingsDisplay();
        $this->dispatch('modal-open:news-settings');
    }

    public function saveSettings(): void
    {
        $this->authorize('news.settings.update');

        $this->validate([
            'latestPostLimit' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        Setting::current()->update([
            'latest_post_limit' => $this->latestPostLimit,
        ]);

        // modal-close:<id> — the Livewire-event listener modal.blade.php
        // registers via $wire.on(), for server-initiated closes. NOT
        // dispatch('close-modal', id): that targets the Alpine/window-event
        // listener instead, and Livewire wraps a PHP-side positional string
        // param into event.detail = [id] (an array) rather than the bare
        // string that listener's equality check expects — so it silently
        // never matches. Confirmed against the real, working WHM reference.
        $this->dispatch('modal-close:news-settings');
    }

    public function syncNow(): void
    {
        $this->authorize('news.settings.update');

        SyncNewsFromPonorogoJob::dispatch();

        $this->dispatch('news-sync-queued');
    }

    protected function refreshSettingsDisplay(): void
    {
        $setting = Setting::current();
        $this->latestPostLimit = $setting->latest_post_limit;
        $this->lastSyncedAt = $setting->last_synced_at?->toDateTimeString();
    }

    public function render()
    {
        $articles = Article::query()
            ->with('category')
            ->when($this->search, fn ($q) => $q->where('judul', 'like', "%{$this->search}%"))
            ->when(! empty($this->categoryId), fn ($q) => $q->whereIn('category_id', $this->categoryId))
            ->orderByDesc('tanggal_terbit')
            ->paginate(20);

        return view('nawasara-news::livewire.pages.news.section.table', [
            'articles' => $articles,
            // Only categories with at least one currently-synced article —
            // per explicit request. A category can exist (synced from WP's
            // full category list) without any of the latest-N articles
            // actually using it; no point offering it as a filter option.
            'categories' => Category::query()->whereHas('articles')->orderBy('slug')->get(),
        ]);
    }
}
