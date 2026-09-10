<?php

namespace Nawasara\News\Livewire\Source;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-news::livewire.pages.source.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
