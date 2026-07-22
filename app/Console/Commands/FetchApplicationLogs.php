<?php

namespace App\Console\Commands;

use App\Models\ApplicationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FetchApplicationLogs extends Command
{
    protected $signature = 'applogs:fetch {--days=1 : Number of days back to fetch}';
    protected $description = 'Fetch application logs from ValueCommerce API and auto-expire jobs with 30+ conversions';

    private const VC_AUTH_URL = 'https://api.valuecommerce.com/auth/v1/affiliate/token/?grant_type=client_credentials';
    private const VC_TRANSACTION_URL = 'https://api.valuecommerce.com/report/v1/affiliate/transaction/';
    private const VC_CREDENTIALS = 'OGM0N2JkZWIwZWUxZGY5ZDYzOGUzZTIyYzZhNTNiODMyMDZkNGVlMnw0NzQ1ZjlkZjM3YjJjNGVhZWRmNmY5ODc1MDQyY2ZlYzM2MmY5NDVi';
    private const EXPIRE_THRESHOLD = 30;

    public function handle(): int
    {
        $days = (int) $this->option('days');

        // Step 1: Get bearer token
        $this->info('Authenticating with ValueCommerce API...');
        $bearerToken = $this->getBearerToken();
        if (!$bearerToken) {
            $this->error('Failed to get bearer token from ValueCommerce.');
            return self::FAILURE;
        }

        // Step 2: Fetch transactions
        $fromDate = now()->subDays($days)->format('Y-m-d');
        $this->info("Fetching transactions from {$fromDate}...");
        $entries = $this->getTransactions($bearerToken, $fromDate);
        if ($entries === null) {
            $this->error('Failed to fetch transactions from ValueCommerce.');
            return self::FAILURE;
        }
        $this->info('Fetched ' . count($entries) . ' entries from API.');

        // Step 3: Filter out duplicates (already in DB from last 3 days)
        $existingIds = ApplicationLog::where('order_date', '>', now()->subDays(3))
            ->pluck('transaction_id')
            ->flip()
            ->toArray();

        $newRecords = [];
        foreach ($entries as $entry) {
            if (isset($existingIds[$entry->transactionOid])) {
                continue;
            }

            if (empty($entry->referrer)) {
                continue;
            }

            // Extract job_no from referrer URL
            if (preg_match('/(?:https?:\/\/(?:www\.)*(?<domain>nihonarubaito)\.com\/jobs\/)(?<job_no>[a-zA-Z0-9]+)/', $entry->referrer, $matched)) {
                if (isset($matched['domain']) && $matched['domain'] === 'nihonarubaito') {
                    $newRecords[strtotime($entry->orderDate)] = [
                        'transaction_id' => $entry->transactionOid,
                        'merchant_name'  => $entry->merchantName,
                        'click_date'     => $entry->clickDate,
                        'order_date'     => $entry->orderDate,
                        'job_no'         => $matched['job_no'],
                    ];
                }
            }
        }

        // Step 4: Insert new records
        if ($newRecords) {
            DB::table('application_logs')->insertOrIgnore(array_values($newRecords));
            $this->info('Inserted ' . count($newRecords) . ' new application log(s).');
        } else {
            $this->info('No new records to insert.');
        }

        // Step 5: Auto-expire jobs with 30+ conversions
        $expired = $this->expireJobs();
        if ($expired > 0) {
            $this->info("Auto-expired {$expired} job(s) with " . self::EXPIRE_THRESHOLD . '+ conversions.');
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function getBearerToken(): ?string
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, self::VC_AUTH_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Host: api.valuecommerce.com',
                'Authorization: Bearer ' . self::VC_CREDENTIALS,
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Connection: close',
            ]);

            $output = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$output) {
                Log::error("ValueCommerce auth failed: HTTP {$httpCode} " . ($output ?: '(empty)'));
                return null;
            }

            $data = json_decode($output);
            return $data->resultSet->rowData->bearer_token ?? null;
        } catch (\Exception $e) {
            Log::error('ValueCommerce auth exception: ' . $e->getMessage());
            return null;
        }
    }

    private function getTransactions(string $bearerToken, string $fromDate): ?array
    {
        try {
            $url = self::VC_TRANSACTION_URL . '?from_date=' . $fromDate . '&limit=1000';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Host: api.valuecommerce.com',
                'Authorization: Bearer ' . $bearerToken,
                'Accept: application/json',
                'Connection: close',
            ]);

            $output = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$output) {
                Log::error("ValueCommerce transactions failed: HTTP {$httpCode} " . ($output ?: '(empty)'));
                return null;
            }

            $data = json_decode($output);
            return $data->resultSet->rowData ?? [];
        } catch (\Exception $e) {
            Log::error('ValueCommerce transactions exception: ' . $e->getMessage());
            return null;
        }
    }

    private function expireJobs(): int
    {
        // Find jobs with 30+ non-expired conversions
        $jobsToExpire = DB::table('application_logs')
            ->select('job_no')
            ->where('expired', 0)
            ->groupBy('job_no')
            ->havingRaw('COUNT(job_no) >= ?', [self::EXPIRE_THRESHOLD])
            ->pluck('job_no')
            ->toArray();

        if (empty($jobsToExpire)) {
            return 0;
        }

        // Mark logs as expired
        DB::table('application_logs')
            ->whereIn('job_no', $jobsToExpire)
            ->update(['expired' => 1]);

        // Set job status to quota full (6)
        DB::table('jobs')
            ->whereIn('job_no', $jobsToExpire)
            ->update(['job_status_id' => 6]);

        return count($jobsToExpire);
    }
}
