<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Api\Application\Actions\DecideSiteAction;
use App\Modules\Api\Application\Queries\SiteApprovalQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final readonly class SiteApprovalController
{
    public function __construct(private SiteApprovalQuery $sites, private DecideSiteAction $decisions) {}

    public function index(): View
    {
        return view('panel::sites.index', ['sites' => $this->sites->all()]);
    }

    public function check(int $ticket): RedirectResponse
    {
        $this->decisions->checkEnamad($ticket);

        return redirect()->route('panel.sites.index');
    }

    public function decide(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        try {
            $this->decisions->decide(
                $ticket, (int) $request->user()->id,
                $data['decision'] === 'approve', $data['note'] ?? '',
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['decision' => $exception->getMessage()]);
        }

        return redirect()->route('panel.sites.index');
    }
}
