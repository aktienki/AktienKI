<?php

namespace App\Livewire\Stocks;

use App\Models\Prediction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $direction = '';
    public string $sort = 'ai_score';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingDirection(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Prediction::query()
            ->with(['company'])
            ->whereHas('company', function (Builder $companyQuery): void {
                $companyQuery->where('active', true);

                if ($this->search !== '') {
                    $term = '%' . trim($this->search) . '%';

                    $companyQuery->where(function (Builder $q) use ($term): void {
                        $q->where('symbol', 'ilike', $term)
                            ->orWhere('name', 'ilike', $term)
                            ->orWhere('isin', 'ilike', $term)
                            ->orWhere('sector', 'ilike', $term)
                            ->orWhere('industry', 'ilike', $term);
                    });
                }
            });

        if ($this->direction !== '' && method_exists(Prediction::class, 'scopeSignal')) {
            $query->signal($this->direction);
        }

        $this->applySorting($query);

        return view('livewire.stocks.index', [
            'predictions' => $query->paginate(24),
        ]);
    }

    private function applySorting(Builder $query): void
    {
        $columns = Schema::getColumnListing('predictions');

        if ($this->sort === 'return' && in_array('expected_return', $columns, true)) {
            $query->orderByDesc('expected_return');
            return;
        }

        if ($this->sort === 'date' && in_array('prediction_date', $columns, true)) {
            $query->orderByDesc('prediction_date');
            return;
        }

        if (in_array('prediction_score', $columns, true)) {
            $query->orderByDesc('prediction_score');
            return;
        }

        if (in_array('buy_probability', $columns, true)) {
            $query->orderByDesc('buy_probability');
            return;
        }

        $query->latest('id');
    }
}