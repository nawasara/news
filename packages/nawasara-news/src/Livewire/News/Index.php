<?php

namespace Nawasara\News\Livewire\News;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-news::livewire.pages.news.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
