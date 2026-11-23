<!doctype html>
<html>
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
@php
  // Public PNG logos (most email apps don't show SVG).
  $nicratLogo = 'https://ncsr.nicrat.gov.ng/assets/img/NCSR.png';
  $resilienceLogo = 'https://ncsr.nicrat.gov.ng/assets/img/resilience.png';
  $s = $summary;
  $delta = function ($now, $prev) {
      if ($prev === null) return '';
      $d = $now - $prev;
      if ($d === 0) return '<span style="color:#6b7280;">same as last week</span>';
      $pct = $prev > 0 ? ' (' . ($d > 0 ? '+' : '') . round($d / $prev * 100) . '%)' : '';
      return '<span style="color:' . ($d > 0 ? '#15803d' : '#b91c1c') . ';">' . ($d > 0 ? '▲ ' : '▼ ') . abs($d) . $pct . '</span>';
  };
@endphp
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:680px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;">
    <tr>
      <td align="center" style="padding:20px 24px 12px;">
        <img src="{{ $nicratLogo }}" alt="NICRAT" height="64" style="display:block;height:64px;width:auto;max-width:240px;border:0;">
      </td>
    </tr>
    <tr>
      <td style="background:#166534;color:#ffffff;padding:20px 24px;">
        <div style="font-size:12px;letter-spacing:1px;text-transform:uppercase;opacity:.8;">National Cancer Screening Register</div>
        <div style="font-size:21px;font-weight:bold;margin-top:4px;">Weekly summary · {{ $s['period']['label'] }}</div>
        <div style="font-size:13px;opacity:.85;margin-top:4px;">{{ $scopeText }}</div>
      </td>
    </tr>
    <tr>
      <td style="padding:24px;">
        <p style="margin:0 0 16px;">Hello {{ $recipientName }}, here is what happened on NCSR this week, compared with the week before ({{ $s['period']['previousLabel'] }}).</p>

        {{-- Headline numbers, two per row so it reads well on phones --}}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:8px;margin:0 -8px 8px;">
          @foreach (array_chunk($s['kpis'], 2) as $pair)
            <tr>
              @foreach ($pair as $k)
                <td width="50%" valign="top" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:14px;">
                  <div style="font-size:12px;color:#6b7280;">{{ $k['label'] }}</div>
                  <div style="font-size:26px;font-weight:bold;color:#111827;margin:4px 0;">{{ number_format($k['value']) }}</div>
                  <div style="font-size:12px;">{!! $delta($k['value'], $k['previous']) !!}</div>
                </td>
              @endforeach
              @if (count($pair) === 1)<td width="50%"></td>@endif
            </tr>
          @endforeach
        </table>

        @if (!empty($s['attention']))
          <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:14px 16px;margin:8px 0 20px;">
            <div style="font-weight:bold;color:#9a3412;margin-bottom:6px;">Needs attention</div>
            @foreach ($s['attention'] as $a)
              <div style="font-size:14px;color:{{ $a['tone'] === 'red' ? '#991b1b' : '#92400e' }};margin:4px 0;">• {{ $a['text'] }}</div>
            @endforeach
          </div>
        @endif

        @if (!empty($s['byCancerType']))
          <div style="font-weight:bold;margin:0 0 8px;">Screening by cancer type</div>
          <table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:14px;margin-bottom:20px;">
            <tr style="background:#f9fafb;text-align:left;">
              <th style="border-bottom:1px solid #e5e7eb;">Cancer type</th>
              <th style="border-bottom:1px solid #e5e7eb;text-align:right;">Screened</th>
              <th style="border-bottom:1px solid #e5e7eb;text-align:right;">Suspicious</th>
            </tr>
            @foreach ($s['byCancerType'] as $r)
              <tr>
                <td style="border-bottom:1px solid #f3f4f6;">{{ $r['cancerType'] }}</td>
                <td style="border-bottom:1px solid #f3f4f6;text-align:right;">{{ number_format($r['screened']) }}</td>
                <td style="border-bottom:1px solid #f3f4f6;text-align:right;{{ $r['suspicious'] ? 'color:#b91c1c;font-weight:bold;' : '' }}">{{ number_format($r['suspicious']) }}</td>
              </tr>
            @endforeach
          </table>
        @endif

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
          <tr>
            <td width="50%" valign="top" style="padding-right:8px;">
              <div style="font-weight:bold;margin-bottom:6px;">Lab</div>
              <div style="font-size:14px;line-height:1.7;">
                Waiting for a result: <b>{{ number_format($s['lab']['awaiting']) }}</b><br>
                Overdue (&gt; 7 days): <b style="{{ $s['lab']['overdue'] ? 'color:#b91c1c;' : '' }}">{{ number_format($s['lab']['overdue']) }}</b><br>
                Avg. sample → result: <b>{{ $s['lab']['avgTurnaroundDays'] !== null ? $s['lab']['avgTurnaroundDays'] . ' days' : '—' }}</b>
              </div>
            </td>
            <td width="50%" valign="top" style="padding-left:8px;">
              <div style="font-weight:bold;margin-bottom:6px;">Referrals &amp; treatment</div>
              <div style="font-size:14px;line-height:1.7;">
                Open referrals: <b>{{ number_format($s['referrals']['open']) }}</b><br>
                Pending &gt; 14 days: <b style="{{ $s['referrals']['waitingOver14'] ? 'color:#b45309;' : '' }}">{{ number_format($s['referrals']['waitingOver14']) }}</b><br>
                @if ($s['referrals']['completedThisWeek'] !== null)Completed this week: <b>{{ number_format($s['referrals']['completedThisWeek']) }}</b><br>@endif
                @if ($s['treatment']['active'] !== null)Active treatment plans: <b>{{ number_format($s['treatment']['active']) }}</b>@endif
                @if ($s['treatment']['newThisWeek'] !== null) ({{ $s['treatment']['newThisWeek'] }} new)@endif
              </div>
            </td>
          </tr>
        </table>

        @if (!empty($s['facilities']))
          <div style="font-weight:bold;margin:0 0 8px;">Facilities this week @if($s['facilityCount'] > count($s['facilities']))<span style="font-weight:normal;color:#6b7280;font-size:12px;">(top {{ count($s['facilities']) }} of {{ $s['facilityCount'] }})</span>@endif</div>
          <table role="presentation" width="100%" cellpadding="7" cellspacing="0" style="border-collapse:collapse;font-size:13px;margin-bottom:20px;">
            <tr style="background:#f9fafb;text-align:left;">
              <th style="border-bottom:1px solid #e5e7eb;">Facility</th>
              <th style="border-bottom:1px solid #e5e7eb;text-align:right;">Screened</th>
              <th style="border-bottom:1px solid #e5e7eb;text-align:right;">Suspicious</th>
              <th style="border-bottom:1px solid #e5e7eb;text-align:right;">Samples</th>
              <th style="border-bottom:1px solid #e5e7eb;text-align:right;">Overdue</th>
            </tr>
            @foreach ($s['facilities'] as $r)
              <tr>
                <td style="border-bottom:1px solid #f3f4f6;">{{ $r['facilityName'] }}</td>
                <td style="border-bottom:1px solid #f3f4f6;text-align:right;">{{ $r['screened'] }}</td>
                <td style="border-bottom:1px solid #f3f4f6;text-align:right;{{ $r['suspicious'] ? 'color:#b91c1c;font-weight:bold;' : '' }}">{{ $r['suspicious'] }}</td>
                <td style="border-bottom:1px solid #f3f4f6;text-align:right;">{{ $r['samples'] }}</td>
                <td style="border-bottom:1px solid #f3f4f6;text-align:right;{{ $r['overdue'] ? 'color:#b91c1c;' : '' }}">{{ $r['overdue'] }}</td>
              </tr>
            @endforeach
          </table>
        @else
          <p style="margin:0 0 20px;color:#6b7280;">No screening activity was recorded this week.</p>
        @endif

        @if (!empty($s['quietFacilities']))
          <div style="font-weight:bold;margin:0 0 6px;">No screening this week (active in the previous 4 weeks)</div>
          <p style="margin:0 0 20px;font-size:13px;color:#4b5563;">
            {{ implode(' · ', $s['quietFacilities']) }}@if($s['quietCount'] > count($s['quietFacilities'])) and {{ $s['quietCount'] - count($s['quietFacilities']) }} more @endif
          </p>
        @endif

        <a href="{{ $link }}" style="display:inline-block;background:#15803d;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:12px;font-weight:bold;">Open Analytics</a>
        <p style="margin:20px 0 0;font-size:12px;color:#6b7280;">
          You receive this weekly summary because you are on the NCSR report list. An administrator can change this under Report Settings.
        </p>
      </td>
    </tr>
    <tr>
      <td align="center" style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:16px 24px;">
        <div style="font-size:11px;color:#6b7280;margin-bottom:8px;">Powered by</div>
        <img src="{{ $resilienceLogo }}" alt="Resilience Nigeria" height="36" style="display:inline-block;height:36px;width:auto;max-width:200px;border:0;">
      </td>
    </tr>
  </table>
</body>
</html>
