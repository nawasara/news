<?php

namespace Nawasara\News\Livewire\Setting;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-news::livewire.pages.setting.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
