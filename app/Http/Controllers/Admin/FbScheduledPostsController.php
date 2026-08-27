<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FbPostedLog;
use App\Services\FbQueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FbScheduledPostsController extends Controller
{
    private FbQueueService $queueService;

    public function __construct(FbQueueService $queueService)
    {
        $this->queueService = $queueService;
    }

    public function index(Request $request)
    {
        $filters = [
            'categories' => $request->input('categories', []),
            'wage_floor' => $request->input('wage_floor'),
            'hook_only' => $request->boolean('hook_only'),
            'affiliate_only' => $request->boolean('affiliate_only'),
            'boost_only' => $request->boolean('boost_only'),
        ];

        // Get queues for each page
        $tokyoQueue = $this->queueService->getPageQueue('tokyo', $filters);
        $kantoQueue = $this->queueService->getPageQueue('kanto', $filters);
        $osakaQueue = $this->queueService->getPageQueue('osaka', $filters);

        // Apply boost-only filter if needed (can't be done in SQL)
        if ($filters['boost_only']) {
            $tokyoQueue = $tokyoQueue->where('boost_eligible', true)->values();
            $kantoQueue = $kantoQueue->where('boost_eligible', true)->values();
            $osakaQueue = $osakaQueue->where('boost_eligible', true)->values();
        }

        // Supporting panels
        $siteOnlyJobs = $this->queueService->getSiteOnlyJobs();
        $sourcingGaps = $this->queueService->getSourcingGaps();

        return view('admin.fb-scheduled-posts.index', compact(
            'tokyoQueue',
            'kantoQueue',
            'osakaQueue',
            'siteOnlyJobs',
            'sourcingGaps',
            'filters'
        ));
    }

    public function markPosted(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'job_no' => 'required|integer',
            'page' => 'required|in:tokyo,kanto,osaka',
            'format' => 'required|in:text,link',
            'was_boosted' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        FbPostedLog::create([
            'job_no' => $request->input('job_no'),
            'page' => $request->input('page'),
            'post_format' => $request->input('format'),
            'was_boosted' => $request->boolean('was_boosted'),
            'posted_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function export(Request $request)
    {
        $page = $request->input('page', 'tokyo');
        $filters = [
            'categories' => $request->input('categories', []),
            'wage_floor' => $request->input('wage_floor'),
            'hook_only' => $request->boolean('hook_only'),
            'affiliate_only' => $request->boolean('affiliate_only'),
            'boost_only' => $request->boolean('boost_only'),
        ];

        $queue = $this->queueService->getPageQueue($page, $filters);

        if ($filters['boost_only']) {
            $queue = $queue->where('boost_eligible', true)->values();
        }

        $filename = "fb-queue-{$page}-" . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($queue) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'Job No',
                'Score',
                'Headline',
                'Format',
                'Station',
                'Category',
                'Wage',
                'Affiliate',
                'Boost Eligible',
                'Post URL',
                'Boost URL',
                'Days Until Expiry',
            ]);

            // Data rows
            foreach ($queue as $item) {
                fputcsv($file, [
                    $item->job->job_no,
                    $item->score,
                    $item->headline,
                    $item->suggested_format,
                    $item->job->station,
                    $this->getCategoryName($item->job->job_category_id),
                    $item->job->wage,
                    $item->is_affiliate ? 'Yes' : 'No',
                    $item->boost_eligible ? 'Yes' : 'No',
                    $item->post_url,
                    $item->boost_url,
                    $item->days_until_expiry,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function getCategoryName(int $categoryId): string
    {
        return match ($categoryId) {
            1 => 'Packing/Sorting',
            2 => 'Restaurant',
            3 => 'Convenience Store',
            4 => 'Bed Making/Cleaning',
            5 => 'Delivery',
            default => 'Other',
        };
    }
}
