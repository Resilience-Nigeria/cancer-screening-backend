<!doctype html>
<html>
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;">
@php
  // Logos: embedded when sent by Laravel's mailer, linked from APP_URL when sent through the Brevo API.
  // Replace the two files to change them:
  //   public/images/email/nicrat-logo.png   and   public/images/email/resilience-logo.png
  
   $nicratLogo = 'https://ncsr.nicrat.gov.ng/assets/img/NCSR.svg';
   $resilienceLogo = 'https://ncsr.nicrat.gov.ng/assets/img/resilience.png';


@endphp
    <tr>
      <td align="center" style="background:#ffffff;padding:20px 24px 12px;">
        @if (isset($message) && file_exists($nicratLogo))
          <img src="{{ $message->embed($nicratLogo) }}" alt="NICRAT" height="64" style="display:block;height:64px;width:auto;border:0;">
        @elseif (!empty($logoBase) && file_exists($nicratLogo))
          <img src="{{ $logoBase }}/images/email/nicrat-logo.png" alt="NICRAT" height="64" style="display:block;height:64px;width:auto;border:0;">
        @else
          <div style="font-size:18px;font-weight:bold;color:#166534;">NICRAT</div>
        @endif
      </td>
    </tr>
    <tr>
      <td style="background:#166534;color:#ffffff;padding:20px 24px;">
        <div style="font-size:12px;letter-spacing:1px;text-transform:uppercase;opacity:.8;">National Cancer Screening Register</div>
        <div style="font-size:20px;font-weight:bold;margin-top:4px;">Test results waiting for more than {{ $data['days'] }} days</div>
      </td>
    </tr>
    <tr>
      <td style="padding:24px;">
        <p style="margin:0 0 12px;">Hello {{ $recipientName }},</p>
        <p style="margin:0 0 16px;">
          <strong>{{ $data['total'] }}</strong> sample{{ $data['total'] === 1 ? '' : 's' }} / test{{ $data['total'] === 1 ? '' : 's' }}
          @php $where = ($data['scope'] ?? 'facilities') === 'all' ? 'across all facilities' : ((($data['scope'] ?? '') === 'facility') ? 'at your facility' : 'in your facilities'); @endphp
          {{ $where }} {{ $data['total'] === 1 ? 'has' : 'have' }} been waiting for a result for more than {{ $data['days'] }} days.
          @if (($data['scope'] ?? '') === 'facility')
            Please follow up with the lab and enter the results.
          @else
            Please follow up with the lab or the facility so the results can be entered.
          @endif
        </p>

        @if (count($data['byFacility'] ?? []) > 1)
          <table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:14px;margin-bottom:16px;">
            <tr style="background:#f9fafb;text-align:left;">
              <th style="border-bottom:1px solid #e5e7eb;">Facility</th>
              <th style="border-bottom:1px solid #e5e7eb;">Waiting</th>
              <th style="border-bottom:1px solid #e5e7eb;">Oldest sample</th>
            </tr>
            @foreach ($data['byFacility'] as $f)
              <tr>
                <td style="border-bottom:1px solid #f3f4f6;">{{ $f['facilityName'] }}@if($f['facilityState']), {{ $f['facilityState'] }}@endif</td>
                <td style="border-bottom:1px solid #f3f4f6;">{{ $f['count'] }}</td>
                <td style="border-bottom:1px solid #f3f4f6;">{{ $f['oldest'] }}</td>
              </tr>
            @endforeach
          </table>
        @endif

        @if (!empty($data['samples']))
          <p style="margin:0 0 8px;font-weight:bold;">Longest waiting</p>
          <table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:13px;margin-bottom:20px;">
            @foreach (array_slice($data['samples'], 0, 15) as $s)
              <tr>
                <td style="border-bottom:1px solid #f3f4f6;">{{ $s['sampleRef'] }}</td>
                <td style="border-bottom:1px solid #f3f4f6;">{{ $s['testLabel'] }}</td>
                <td style="border-bottom:1px solid #f3f4f6;">{{ $s['clientName'] ?? $s['clientId'] }}</td>
                <td style="border-bottom:1px solid #f3f4f6;">{{ $s['facility'] }}</td>
                <td style="border-bottom:1px solid #f3f4f6;white-space:nowrap;">{{ $s['daysWaiting'] }} days</td>
              </tr>
            @endforeach
          </table>
        @endif

        <a href="{{ $link }}" style="display:inline-block;background:#15803d;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:12px;font-weight:bold;">
          Open Samples &amp; Test Results
        </a>
        <p style="margin:20px 0 0;font-size:12px;color:#6b7280;">You receive this daily while results are overdue. It stops once they are entered.</p>
      </td>
    </tr>
    <tr>
      <td align="center" style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:16px 24px;">
        <div style="font-size:11px;color:#6b7280;margin-bottom:8px;">Powered by</div>
        @if (isset($message) && file_exists($resilienceLogo))
          <img src="{{ $message->embed($resilienceLogo) }}" alt="Resilience Nigeria" height="36" style="display:inline-block;height:36px;width:auto;border:0;">
        @elseif (!empty($logoBase) && file_exists($resilienceLogo))
          <img src="{{ $logoBase }}/images/email/resilience-logo.png" alt="Resilience Nigeria" height="36" style="display:inline-block;height:36px;width:auto;border:0;">
        @else
          <div style="font-size:13px;font-weight:bold;color:#374151;">Resilience Nigeria</div>
        @endif
      </td>
    </tr>
  </table>
</body>
</html>
