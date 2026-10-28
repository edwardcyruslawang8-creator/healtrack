<?php
/**
 * "X-ray Findings" image box — shared by every role's
 * laboratory_result_details.php. Shows the real file uploaded for THIS
 * xray_results doc ($xrayExam['id'], resolved by hc_resolve_xray_result()
 * for this patient), streamed through api/download-xray-result.php (which
 * re-checks the signed-in role). Images are scaled to fit whole
 * (object-fit: contain); a PDF result is embedded; nothing uploaded, a
 * file missing from storage, or a failed load each show a placeholder
 * instead of a broken image.
 *
 * Expects $xrayExam (with 'id' and 'preview') in scope.
 */

$xrayPreviewKind = $xrayExam['preview']['kind'] ?? 'none';
$xrayPreviewUrl = BASE_URL . '/api/download-xray-result.php?id=' . urlencode((string) ($xrayExam['id'] ?? '')) . '&view=1';
?>
<?php if ($xrayPreviewKind === 'image' || $xrayPreviewKind === 'pdf'): ?>
<div class="lrd-xray-image lrd-xray-image--file">
    <?php if ($xrayPreviewKind === 'image'): ?>
    <a href="<?= htmlspecialchars($xrayPreviewUrl) ?>" target="_blank" rel="noopener" class="lrd-xray-image-link" title="Open full-size image">
        <img src="<?= htmlspecialchars($xrayPreviewUrl) ?>" alt="<?= htmlspecialchars($xrayExam['examName'] . ' image') ?>" onerror="this.closest('.lrd-xray-image').classList.add('lrd-xray-image--error')">
    </a>
    <?php else: ?>
    <iframe src="<?= htmlspecialchars($xrayPreviewUrl) ?>" title="<?= htmlspecialchars($xrayExam['examName'] . ' result file') ?>"></iframe>
    <?php endif; ?>
    <div class="lrd-xray-image-fallback">
        <i class="bi bi-exclamation-triangle"></i>
        <span>The X-ray image could not be loaded.</span>
    </div>
</div>
<?php else: ?>
<div class="lrd-xray-image">
    <i class="bi bi-lungs"></i>
    <span><?= $xrayPreviewKind === 'missing' ? 'X-ray image file is no longer available.' : 'No X-ray image uploaded.' ?></span>
</div>
<?php endif; ?>
