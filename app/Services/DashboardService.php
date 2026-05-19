<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ScreeningVisit;  // Changed from Visit
use App\Models\CaseOutcome;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardService
{
    /**
     * Get comprehensive dashboard statistics
     */
    public function getDashboardStats(): array
    {
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        // Total registered clients
        $totalClients = Client::count();

        // Screenings this month (total visits this month)
        $screeningsThisMonth = ScreeningVisit::whereMonth('visitDate', $currentMonth)
            ->whereYear('visitDate', $currentYear)
            ->count();

        // Pending follow-ups (visits with follow_up type that don't have completed outcomes)
        $pendingFollowUps = ScreeningVisit::where('visitType', 'follow_up')
            ->whereDoesntHave('caseOutcome', function ($query) {
                $query->where('treatmentCompleted', true);
            })
            ->count();

        // Referral alerts (confirmed cancer cases requiring linkage to treatment)
        $referralAlerts = CaseOutcome::where('cancerConfirmed', true)
            ->where(function ($query) {
                $query->where('linkageToTreatment', false)
                    ->orWhereNull('linkageToTreatment')
                    ->orWhere('followUpStatus', 'pending');
            })
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->count();

        // Screening counts by module
        $cervicalScreenings = DB::table('cervical_screenings')->count();
        $breastScreenings = DB::table('breast_screenings')->count();
        $prostateScreenings = DB::table('prostate_screenings')->count();
        $colorectalScreenings = DB::table('colorectal_screenings')->count();
        $liverScreenings = DB::table('liver_screenings')->count();

        // Positive findings (confirmed cancer cases)
        $positiveFindings = CaseOutcome::where('screeningResult', 'positive')
            ->count();

        return [
            'totalClients' => $totalClients,
            'screeningsThisMonth' => $screeningsThisMonth,
            'pendingFollowUps' => $pendingFollowUps,
            'referralAlerts' => $referralAlerts,
            'cervicalScreenings' => $cervicalScreenings,
            'breastScreenings' => $breastScreenings,
            'prostateScreenings' => $prostateScreenings,
            'colorectalScreenings' => $colorectalScreenings,
            'liverScreenings' => $liverScreenings,
            'positiveFindings' => $positiveFindings,
        ];
    }

    /**
     * Get recent screening activity with pagination
     */
    public function getRecentActivity(int $page = 1, int $limit = 6): array
    {
        $offset = ($page - 1) * $limit;

        $visits = ScreeningVisit::with(['client.facility', 'cervicalScreening', 'breastScreening', 
                               'prostateScreening', 'colorectalScreening', 'liverScreening', 'caseOutcome'])
            ->orderBy('visitDate', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        $total = ScreeningVisit::count();

        $activities = $visits->map(function ($visit) {
            return [
                'visitId' => $visit->visitId,
                'clientName' => $visit->client->fullName ?? 'Unknown',
                'clientId' => $visit->client->clientId ?? '—',
                'screeningType' => $this->determineScreeningType($visit),
                'status' => $this->determineVisitStatus($visit),
                'facility' => $visit->client->facility->facilityName ?? '—',
                'date' => $visit->visitDate,
                'visitDate' => $visit->visitDate,
                'visit_date' => $visit->visitDate,
                'client' => [
                    'fullName' => $visit->client->fullName ?? 'Unknown',
                    'full_name' => $visit->client->fullName ?? 'Unknown',
                    'screeningId' => $visit->client->screeningId ?? '—',
                    'screening_id' => $visit->client->screeningId ?? '—',
                ],
                'facility' => [
                    'facilityName' => $visit->client->facility->facilityName ?? '—',
                    'facility_name' => $visit->client->facility->facilityName ?? '—',
                ],
            ];
        });

        return [
            'data' => $activities,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * Get monthly trends for charts
     */
    public function getMonthlyTrends(): array
    {
        $months = [];
        $screenings = [];
        $referrals = [];

        // Get last 7 months
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $month = $date->format('M');
            $year = $date->year;
            $monthNum = $date->month;

            $months[] = $month;

            // Count screenings (visits) for this month
            $screeningCount = ScreeningVisit::whereMonth('visitDate', $monthNum)
                ->whereYear('visitDate', $year)
                ->count();

            $screenings[] = $screeningCount;

            // Count referrals (confirmed cancer cases needing linkage) for this month
            $referralCount = CaseOutcome::where('cancerConfirmed', true)
                ->where(function ($query) {
                    $query->where('linkageToTreatment', false)
                        ->orWhereNull('linkageToTreatment');
                })
                ->whereMonth('created_at', $monthNum)
                ->whereYear('created_at', $year)
                ->count();

            $referrals[] = $referralCount;
        }

        return [
            'labels' => $months,
            'screenings' => $screenings,
            'referrals' => $referrals,
        ];
    }

    /**
     * Determine the primary screening type for a visit
     */
    private function determineScreeningType(ScreeningVisit $visit): string
    {
        if ($visit->cervicalScreening) return 'Cervical Screening';
        if ($visit->breastScreening) return 'Breast Screening';
        if ($visit->prostateScreening) return 'Prostate Screening';
        if ($visit->colorectalScreening) return 'Colorectal Screening';
        if ($visit->liverScreening) return 'Liver Screening';
        
        return 'General Screening';
    }

    /**
     * Determine visit status based on completion and case outcomes
     */
    private function determineVisitStatus(ScreeningVisit $visit): string
    {
        // Check if visit has a case outcome
        $caseOutcome = $visit->caseOutcome;

        if ($caseOutcome) {
            // If cancer confirmed and needs linkage
            if ($caseOutcome->cancerConfirmed && !$caseOutcome->linkageToTreatment) {
                return 'Referred';
            }
            
            // If treatment completed
            if ($caseOutcome->treatmentCompleted) {
                return 'Completed';
            }

            // If cancer confirmed but treatment ongoing
            if ($caseOutcome->cancerConfirmed) {
                return 'Follow-up';
            }

            // If no cancer confirmed
            if ($caseOutcome->cancerConfirmed === false) {
                return 'Completed';
            }
        }

        // Check if any screening module is completed
        $hasScreening = $visit->cervicalScreening || 
                       $visit->breastScreening || 
                       $visit->prostateScreening || 
                       $visit->colorectalScreening || 
                       $visit->liverScreening;

        if ($hasScreening) {
            return 'Completed';
        }

        // Check if it's a follow-up visit
        if ($visit->visitType === 'follow_up') {
            return 'Follow-up';
        }

        return 'Pending';
    }
}