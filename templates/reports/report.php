<!doctype html>
<html lang="en-GB"><head><meta charset="utf-8"><title><?= $e($title) ?></title>
<style>
  body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 10px; color: #222; }
  h1 { font-size: 16px; margin: 0 0 2px; } .org { font-size: 11px; color: #555; margin-bottom: 8px; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; } th, td { border: 1px solid #bbb; padding: 3px 4px; text-align: left; vertical-align: top; }
  th { background: #eef2f7; } .sum td { border: none; padding: 1px 4px; } .foot { margin-top: 10px; font-size: 9px; color: #666; }
  @media print { .noprint { display: none; } }
</style></head>
<body>
<div class="org">Lubie Construction Works · Project Monitoring Dashboard</div>
<h1><?= $e($title) ?></h1>
<?php if ($summary): ?>
<table class="sum"><?php foreach ($summary as $k => $v): ?><tr><td><strong><?= $e($k) ?></strong></td><td><?= $e($v) ?></td></tr><?php endforeach; ?></table>
<?php endif; ?>
<table>
<thead><tr><?php foreach ($headers as $h): ?><th><?= $e($h) ?></th><?php endforeach; ?></tr></thead>
<tbody>
<?php if (!$rows): ?><tr><td colspan="<?= count($headers) ?>">No records.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?><tr><?php foreach ($r as $c): ?><td><?= $e($c) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
</tbody></table>
<div class="foot">Generated <?= $e($generated) ?> by <?= $e($by) ?>. Amounts in GH₵. Figures use only PM-approved progress. Dates DD/MM/YYYY.</div>
<p class="noprint">To save as PDF, use your browser's Print and choose "Save as PDF".</p>
</body></html>
