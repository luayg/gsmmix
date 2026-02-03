@php
  $row = $row ?? ($order ?? null);
  $routePrefix = $routePrefix ?? 'admin.orders.imei';

  // ✅ تنظيف: JSON name + decode entities + إزالة الزوائد
  $cleanText = function ($v) {
    $v = (string)$v;
    $v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // يحل مشكلة &amp;#10060;
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    return trim($v);
  };

  $pickName = function ($v) use ($cleanText) {
    if (is_string($v)) {
      $s = trim($v);
      if ($s !== '' && $s[0] === '{') {
        $j = json_decode($s, true);
        if (is_array($j)) {
          $v = $j['en'] ?? $j['fallback'] ?? reset($j) ?? $v;
        }
      }
    }
    return $cleanText($v);
  };

  // Normalize response to array
  $resp = $row?->response ?? null;
  if (is_string($resp)) {
    $decoded = json_decode($resp, true);
    $resp = is_array($decoded) ? $decoded : [];
  }
  if (!is_array($resp)) $resp = [];

  $items = isset($resp['result_items']) && is_array($resp['result_items']) ? $resp['result_items'] : [];
  $img   = $resp['result_image'] ?? null;

  $isSafeImg = function ($url) {
    if (!is_string($url)) return false;
    $u = trim($url);
    return str_starts_with($u, 'http://') || str_starts_with($u, 'https://') || str_starts_with($u, 'data:image/');
  };

  // ✅ اسم الخدمة الصحيح + تنظيف
  $serviceName = $row?->service?->name ?? ($row->service_name ?? '—');
  $serviceName = $pickName($serviceName);

  // ✅ API service (بنفس الاسم حالياً)
  $apiServiceName = $serviceName;

  // ✅ Provider name
  $providerName = $row?->provider?->name ?? ($row->provider_name ?? '—');
  $providerName = $cleanText($providerName);

  // ✅ Reply: إذا ما عندك provider_reply_html نولدها من response الموجود
  $providerReplyHtml = $resp['provider_reply_html'] ?? '';

  if (trim((string)$providerReplyHtml) === '') {
    if (!empty($resp['result_text'])) {
      $providerReplyHtml = '<pre style="white-space:pre-wrap;">'.e($cleanText($resp['result_text'])).'</pre>';
    } elseif (!empty($resp['message'])) {
      $providerReplyHtml = '<div>'.e($cleanText($resp['message'])).'</div>';
    } elseif (!empty($items)) {
      // توليد Reply من result_items
      $html = '';
      if ($img && $isSafeImg($img)) {
        $html .= '<div style="text-align:center;margin-bottom:10px;"><img src="'.e($img).'" style="max-width:260px;height:auto;" /></div>';
      }
      $html .= '<table class="table table-sm table-bordered"><tbody>';
      foreach ($items as $it) {
        $label = is_array($it) ? ($it['label'] ?? '') : '';
        $value = is_array($it) ? ($it['value'] ?? '') : '';
        $html .= '<tr><th style="width:220px;">'.e($cleanText($label)).'</th><td>'.e($cleanText($value)).'</td></tr>';
      }
      $html .= '</tbody></table>';
      $providerReplyHtml = $html;
    }
  }

  // بقية الحقول
  $userEmail = $cleanText($row?->email ?? ($row->user_email ?? '—'));
  $device    = $cleanText($row?->device ?? ($row->imei ?? '—'));
  $remoteId  = $cleanText($row?->remote_id ?? '—');
  $ip        = $cleanText($row?->ip ?? '—');

  $createdAt = optional($row?->created_at)->format('d/m/Y H:i:s') ?? '—';
  $repliedAt = optional($row?->replied_at)->format('d/m/Y H:i:s') ?? '—';

  $apiOrder  = ($row?->api_order ?? false) ? 'Yes' : 'No';

  $orderPrice = $row?->price;
  $apiCost    = $row?->order_price;
  $profit     = $row?->profit;

  $fmt = function ($v) {
    if ($v === null || $v === '') return '—';
    if (is_numeric($v)) return '$' . number_format((float)$v, 2);
    return (string)$v;
  };

  $curStatus = $row?->status ?? 'waiting';
@endphp
