<?php

namespace Nawasara\News\Livewire\Article;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-news::livewire.pages.article.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
