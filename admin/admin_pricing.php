<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;
use nexpell\LanguageService;

global $_database, $languageService;

AccessControl::checkAdminAccess('pricing');

if ($languageService instanceof LanguageService) {
    $languageService->readPluginModule('pricing');
}

function pricing_admin_ensure_schema(mysqli $database): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $requiredColumns = [
        'title_de' => "ALTER TABLE plugins_pricing_plans ADD COLUMN title_de VARCHAR(100) NOT NULL DEFAULT '' AFTER title",
        'title_en' => "ALTER TABLE plugins_pricing_plans ADD COLUMN title_en VARCHAR(100) NOT NULL DEFAULT '' AFTER title_de",
        'title_it' => "ALTER TABLE plugins_pricing_plans ADD COLUMN title_it VARCHAR(100) NOT NULL DEFAULT '' AFTER title_en",
        'price_unit_de' => "ALTER TABLE plugins_pricing_plans ADD COLUMN price_unit_de VARCHAR(50) NOT NULL DEFAULT '' AFTER price_unit",
        'price_unit_en' => "ALTER TABLE plugins_pricing_plans ADD COLUMN price_unit_en VARCHAR(50) NOT NULL DEFAULT '' AFTER price_unit_de",
        'price_unit_it' => "ALTER TABLE plugins_pricing_plans ADD COLUMN price_unit_it VARCHAR(50) NOT NULL DEFAULT '' AFTER price_unit_en",
        'button_text_de' => "ALTER TABLE plugins_pricing_plans ADD COLUMN button_text_de VARCHAR(100) NOT NULL DEFAULT '' AFTER price_unit_it",
        'button_text_en' => "ALTER TABLE plugins_pricing_plans ADD COLUMN button_text_en VARCHAR(100) NOT NULL DEFAULT '' AFTER button_text_de",
        'button_text_it' => "ALTER TABLE plugins_pricing_plans ADD COLUMN button_text_it VARCHAR(100) NOT NULL DEFAULT '' AFTER button_text_en",
        'feature_text_de' => "ALTER TABLE plugins_pricing_features ADD COLUMN feature_text_de VARCHAR(255) NOT NULL DEFAULT '' AFTER feature_text",
        'feature_text_en' => "ALTER TABLE plugins_pricing_features ADD COLUMN feature_text_en VARCHAR(255) NOT NULL DEFAULT '' AFTER feature_text_de",
        'feature_text_it' => "ALTER TABLE plugins_pricing_features ADD COLUMN feature_text_it VARCHAR(255) NOT NULL DEFAULT '' AFTER feature_text_en",
    ];

    foreach ($requiredColumns as $column => $sql) {
        $table = str_starts_with($column, 'feature_') ? 'plugins_pricing_features' : 'plugins_pricing_plans';
        $check = $database->query("SHOW COLUMNS FROM {$table} LIKE '" . $database->real_escape_string($column) . "'");
        if ($check instanceof mysqli_result && $check->num_rows === 0) {
            $database->query($sql);
        }
    }

    $database->query("UPDATE plugins_pricing_plans
        SET
            title_de = IF(title_de = '', title, title_de),
            title_en = IF(title_en = '', title, title_en),
            title_it = IF(title_it = '', title, title_it),
            price_unit_de = IF(price_unit_de = '', price_unit, price_unit_de),
            price_unit_en = IF(price_unit_en = '', price_unit, price_unit_en),
            price_unit_it = IF(price_unit_it = '', price_unit, price_unit_it)");

    $database->query("UPDATE plugins_pricing_features
        SET
            feature_text_de = IF(feature_text_de = '', feature_text, feature_text_de),
            feature_text_en = IF(feature_text_en = '', feature_text, feature_text_en),
            feature_text_it = IF(feature_text_it = '', feature_text, feature_text_it)");

    $done = true;
}

function pricing_post_text(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function pricing_admin_url(string $action = '', int $id = 0): string
{
    $params = ['site=admin_pricing'];
    if ($action !== '') {
        $params[] = 'action=' . rawurlencode($action);
    }
    if ($id > 0) {
        $params[] = 'id=' . $id;
    }

    return 'admincenter.php?' . implode('&', $params);
}

function pricing_admin_plan(mysqli $database, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $stmt = $database->prepare('SELECT * FROM plugins_pricing_plans WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $plan = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $plan ?: null;
}

function pricing_admin_features(mysqli $database, int $planId): array
{
    $features = [];
    $stmt = $database->prepare('SELECT * FROM plugins_pricing_features WHERE plan_id = ? ORDER BY id ASC');
    $stmt->bind_param('i', $planId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($result && ($row = $result->fetch_assoc())) {
        $features[] = $row;
    }
    $stmt->close();

    return $features;
}

function pricing_admin_lang_switcher(string $groupId, array $languages, string $currentLanguage): string
{
    ob_start();
    ?>
    <div class="btn-group btn-group-sm pricing-lang-switch" data-group="<?= htmlspecialchars($groupId, ENT_QUOTES, 'UTF-8') ?>" role="group" aria-label="Language Switcher">
      <?php foreach ($languages as $code => $label): ?>
        <button type="button" class="btn <?= $code === $currentLanguage ? 'btn-primary active' : 'btn-secondary' ?>" data-lang="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"><?= strtoupper($code) ?></button>
      <?php endforeach; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

function pricing_admin_languages(mysqli $database): array
{
    $languages = [];
    $res = $database->query("SELECT iso_639_1, name_de FROM settings_languages WHERE active = 1 ORDER BY id ASC");
    while ($res && ($row = $res->fetch_assoc())) {
        $iso = strtolower((string) ($row['iso_639_1'] ?? ''));
        if ($iso === '') {
            continue;
        }
        $languages[$iso] = (string) ($row['name_de'] ?? strtoupper($iso));
    }

    if (empty($languages)) {
        $languages = ['de' => 'Deutsch', 'en' => 'English', 'it' => 'Italiano'];
    }

    return $languages;
}

function pricing_admin_current_language(LanguageService $languageService, array $languages): string
{
    if (!empty($_SESSION['language'])) {
        $lang = strtolower((string) $_SESSION['language']);
    } else {
        $lang = strtolower((string) $languageService->detectLanguage());
    }

    if (!isset($languages[$lang])) {
        $lang = (string) array_key_first($languages);
    }

    return $lang;
}

pricing_admin_ensure_schema($_database);

$languages = pricing_admin_languages($_database);
$currentLanguage = pricing_admin_current_language($languageService, $languages);
$action = trim((string) ($_GET['action'] ?? ''));
$currentId = (int) ($_GET['id'] ?? 0);

if (isset($_GET['delete_plan'])) {
    $id = (int) $_GET['delete_plan'];
    $_database->query("DELETE FROM plugins_pricing_features WHERE plan_id = $id");
    $_database->query("DELETE FROM plugins_pricing_plans WHERE id = $id");
    nx_audit_delete('admin_pricing', (string) $id, (string) $id, pricing_admin_url());
    nx_redirect(pricing_admin_url(), 'success', 'alert_deleted', false);
}

if (isset($_GET['delete_feature'])) {
    $id = (int) $_GET['delete_feature'];
    $_database->query("DELETE FROM plugins_pricing_features WHERE id = $id");
    nx_audit_delete('admin_pricing', (string) $id, (string) $id, pricing_admin_url('features', $currentId));
    nx_redirect(pricing_admin_url('features', $currentId), 'success', 'alert_deleted', false);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plan'])) {
    $id = (int) ($_POST['id'] ?? 0);
    $titleDe = pricing_post_text('title_de');
    $titleEn = pricing_post_text('title_en');
    $titleIt = pricing_post_text('title_it');
    $price = (float) ($_POST['price'] ?? 0);
    $unitDe = pricing_post_text('price_unit_de');
    $unitEn = pricing_post_text('price_unit_en');
    $unitIt = pricing_post_text('price_unit_it');
    $buttonDe = pricing_post_text('button_text_de');
    $buttonEn = pricing_post_text('button_text_en');
    $buttonIt = pricing_post_text('button_text_it');
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $isAdvanced = isset($_POST['is_advanced']) ? 1 : 0;
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $targetUrl = trim((string) ($_POST['target_url'] ?? ''));
    $titleFallback = $titleDe !== '' ? $titleDe : ($titleEn !== '' ? $titleEn : $titleIt);
    $unitFallback = $unitDe !== '' ? $unitDe : ($unitEn !== '' ? $unitEn : $unitIt);

    if ($id === 0) {
        $stmt = $_database->prepare("INSERT INTO plugins_pricing_plans (title, title_de, title_en, title_it, target_url, price, price_unit, price_unit_de, price_unit_en, price_unit_it, button_text_de, button_text_en, button_text_it, is_featured, is_advanced, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssdsssssssiii', $titleFallback, $titleDe, $titleEn, $titleIt, $targetUrl, $price, $unitFallback, $unitDe, $unitEn, $unitIt, $buttonDe, $buttonEn, $buttonIt, $isFeatured, $isAdvanced, $sortOrder);
        $stmt->execute();
        $newId = (int) ($_database->insert_id ?? 0);
        $stmt->close();
        nx_audit_create('admin_pricing', (string) $newId, $titleFallback, pricing_admin_url('edit', $newId));
        nx_redirect(pricing_admin_url('edit', $newId), 'success', 'alert_saved', false);
    }

    $stmt = $_database->prepare("UPDATE plugins_pricing_plans SET title = ?, title_de = ?, title_en = ?, title_it = ?, target_url = ?, price = ?, price_unit = ?, price_unit_de = ?, price_unit_en = ?, price_unit_it = ?, button_text_de = ?, button_text_en = ?, button_text_it = ?, is_featured = ?, is_advanced = ?, sort_order = ? WHERE id = ?");
    $stmt->bind_param('sssssdsssssssiiii', $titleFallback, $titleDe, $titleEn, $titleIt, $targetUrl, $price, $unitFallback, $unitDe, $unitEn, $unitIt, $buttonDe, $buttonEn, $buttonIt, $isFeatured, $isAdvanced, $sortOrder, $id);
    $stmt->execute();
    $stmt->close();
    nx_audit_update('admin_pricing', (string) $id, true, $titleFallback, pricing_admin_url('edit', $id));
    nx_redirect(pricing_admin_url('edit', $id), 'success', 'alert_saved', false);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_feature'])) {
    $id = (int) ($_POST['id'] ?? 0);
    $planId = (int) ($_POST['plan_id'] ?? 0);
    $textDe = pricing_post_text('feature_text_de');
    $textEn = pricing_post_text('feature_text_en');
    $textIt = pricing_post_text('feature_text_it');
    $available = isset($_POST['available']) ? 1 : 0;
    $textFallback = $textDe !== '' ? $textDe : ($textEn !== '' ? $textEn : $textIt);

    if ($id === 0) {
        $stmt = $_database->prepare("INSERT INTO plugins_pricing_features (plan_id, feature_text, feature_text_de, feature_text_en, feature_text_it, available) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('issssi', $planId, $textFallback, $textDe, $textEn, $textIt, $available);
        $stmt->execute();
        $newId = (int) ($_database->insert_id ?? 0);
        $stmt->close();
        nx_audit_create('admin_pricing', (string) $newId, $textFallback, pricing_admin_url('features', $planId));
        nx_redirect(pricing_admin_url('features', $planId), 'success', 'alert_saved', false);
    }

    $stmt = $_database->prepare("UPDATE plugins_pricing_features SET feature_text = ?, feature_text_de = ?, feature_text_en = ?, feature_text_it = ?, available = ? WHERE id = ?");
    $stmt->bind_param('ssssii', $textFallback, $textDe, $textEn, $textIt, $available, $id);
    $stmt->execute();
    $stmt->close();
    nx_audit_update('admin_pricing', (string) $id, true, $textFallback, pricing_admin_url('features', $planId));
    nx_redirect(pricing_admin_url('features', $planId), 'success', 'alert_saved', false);
}

$plans = [];
$res = $_database->query("SELECT * FROM plugins_pricing_plans ORDER BY sort_order ASC, id ASC");
while ($res && ($row = $res->fetch_assoc())) {
    $plans[] = $row;
}

$selectedPlan = pricing_admin_plan($_database, $currentId);
$selectedFeatures = $selectedPlan !== null ? pricing_admin_features($_database, (int) $selectedPlan['id']) : [];
?>
<div class="card shadow-sm mt-4">
  <div class="card-header">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
      <div class="card-title mb-0"><i class="bi bi-cash-stack"></i> <?= $languageService->get('title_pricing') ?></div>
      <div class="d-flex gap-2 flex-wrap">
        <?php if ($action !== ''): ?>
          <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(pricing_admin_url(), ENT_QUOTES, 'UTF-8') ?>"><?= $languageService->get('action_back_overview') ?></a>
        <?php endif; ?>
        <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars(pricing_admin_url('new'), ENT_QUOTES, 'UTF-8') ?>"><?= $languageService->get('title_new_plan') ?></a>
      </div>
    </div>
  </div>
  <div class="card-body">
    <?php if ($action === 'new'): ?>
      <div class="border rounded-3 p-3 p-lg-4 bg-light-subtle">
        <h5 class="mb-1"><?= $languageService->get('title_new_plan') ?></h5>
        <p class="text-muted mb-4"><?= $languageService->get('info_admin_grouping') ?></p>
        <form method="post">
          <input type="hidden" name="id" value="0"><input type="hidden" name="save_plan" value="1">
          <div class="border rounded-3 p-3 mb-3 bg-white">
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
              <h6 class="mb-0"><?= $languageService->get('section_texts') ?></h6>
              <?= pricing_admin_lang_switcher('new-plan-texts', $languages, $currentLanguage) ?>
            </div>
            <?php foreach ($languages as $iso => $label): ?>
              <div class="pricing-lang-row" data-group="new-plan-texts" data-lang="<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>">
                <div class="row g-3">
                  <div class="col-12 col-md-6"><label class="form-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> <?= $languageService->get('label_title') ?></label><input class="form-control" name="title_<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>" <?= $iso === 'de' ? 'required' : '' ?>></div>
                  <div class="col-12 col-md-6"><label class="form-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> CTA</label><input class="form-control" name="button_text_<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="border rounded-3 p-3 mb-3 bg-white">
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
              <h6 class="mb-0"><?= $languageService->get('section_pricing') ?></h6>
              <?= pricing_admin_lang_switcher('new-plan-pricing', $languages, $currentLanguage) ?>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-12 col-md-3"><label class="form-label"><?= $languageService->get('label_price') ?></label><input class="form-control" type="number" step="0.01" name="price" required></div>
              <div class="col-12 col-md-3"><label class="form-label"><?= $languageService->get('label_sort') ?></label><input class="form-control" type="number" name="sort_order" value="0"></div>
              <div class="col-12 col-md-3 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" id="is_featured_new"><label class="form-check-label" for="is_featured_new"><?= $languageService->get('label_featured') ?></label></div></div>
              <div class="col-12 col-md-3 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_advanced" id="is_advanced_new"><label class="form-check-label" for="is_advanced_new"><?= $languageService->get('label_premium') ?></label></div></div>
            </div>
            <?php foreach ($languages as $iso => $label): ?>
              <div class="pricing-lang-row" data-group="new-plan-pricing" data-lang="<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>">
                <label class="form-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> <?= $languageService->get('label_unit') ?></label>
                <input class="form-control" name="price_unit_<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>">
              </div>
            <?php endforeach; ?>
          </div>
          <div class="border rounded-3 p-3 mb-4 bg-white">
            <h6 class="mb-2"><?= $languageService->get('section_redirect') ?></h6>
            <p class="text-muted mb-3"><?= $languageService->get('info_target_url') ?></p>
            <label class="form-label"><?= $languageService->get('label_url') ?></label>
            <input class="form-control" name="target_url" placeholder="<?= htmlspecialchars($languageService->get('placeholder_url'), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="text-end"><button class="btn btn-primary"><?= $languageService->get('action_add') ?></button></div>
        </form>
      </div>
    <?php elseif ($action === 'edit' && $selectedPlan !== null): ?>
      <div class="border rounded-3 p-3 p-lg-4 bg-light-subtle">
        <div class="mb-4">
          <h5 class="mb-1"><?= htmlspecialchars((string) ($selectedPlan['title_de'] ?? $selectedPlan['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h5>
          <p class="text-muted mb-0">ID #<?= (int) $selectedPlan['id'] ?></p>
        </div>
        <form method="post">
          <input type="hidden" name="id" value="<?= (int) $selectedPlan['id'] ?>"><input type="hidden" name="save_plan" value="1">
          <div class="border rounded-3 p-3 mb-3 bg-white">
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
              <h6 class="mb-0"><?= $languageService->get('section_texts') ?></h6>
              <?= pricing_admin_lang_switcher('edit-plan-texts-' . (int) $selectedPlan['id'], $languages, $currentLanguage) ?>
            </div>
            <?php foreach ($languages as $iso => $label): ?>
              <div class="pricing-lang-row" data-group="edit-plan-texts-<?= (int) $selectedPlan['id'] ?>" data-lang="<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>">
                <div class="row g-3">
                  <div class="col-12 col-md-6"><label class="form-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> <?= $languageService->get('label_title') ?></label><input class="form-control" name="title_<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) ($selectedPlan['title_' . $iso] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $iso === 'de' ? 'required' : '' ?>></div>
                  <div class="col-12 col-md-6"><label class="form-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> CTA</label><input class="form-control" name="button_text_<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) ($selectedPlan['button_text_' . $iso] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="border rounded-3 p-3 mb-3 bg-white">
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
              <h6 class="mb-0"><?= $languageService->get('section_pricing') ?></h6>
              <?= pricing_admin_lang_switcher('edit-plan-pricing-' . (int) $selectedPlan['id'], $languages, $currentLanguage) ?>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-12 col-md-3"><label class="form-label"><?= $languageService->get('label_price') ?></label><input class="form-control" type="number" step="0.01" name="price" value="<?= htmlspecialchars((string) ($selectedPlan['price'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
              <div class="col-12 col-md-3"><label class="form-label"><?= $languageService->get('label_sort') ?></label><input class="form-control" type="number" name="sort_order" value="<?= (int) ($selectedPlan['sort_order'] ?? 0) ?>"></div>
              <div class="col-12 col-md-3 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" id="featured_<?= (int) $selectedPlan['id'] ?>" <?= !empty($selectedPlan['is_featured']) ? 'checked' : '' ?>><label class="form-check-label" for="featured_<?= (int) $selectedPlan['id'] ?>"><?= $languageService->get('label_featured') ?></label></div></div>
              <div class="col-12 col-md-3 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_advanced" id="advanced_<?= (int) $selectedPlan['id'] ?>" <?= !empty($selectedPlan['is_advanced']) ? 'checked' : '' ?>><label class="form-check-label" for="advanced_<?= (int) $selectedPlan['id'] ?>"><?= $languageService->get('label_premium') ?></label></div></div>
            </div>
            <?php foreach ($languages as $iso => $label): ?>
              <div class="pricing-lang-row" data-group="edit-plan-pricing-<?= (int) $selectedPlan['id'] ?>" data-lang="<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>">
                <label class="form-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> <?= $languageService->get('label_unit') ?></label>
                <input class="form-control" name="price_unit_<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) ($selectedPlan['price_unit_' . $iso] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
              </div>
            <?php endforeach; ?>
          </div>
          <div class="border rounded-3 p-3 mb-4 bg-white">
            <h6 class="mb-2"><?= $languageService->get('section_redirect') ?></h6>
            <p class="text-muted mb-3"><?= $languageService->get('info_target_url') ?></p>
            <label class="form-label"><?= $languageService->get('label_url') ?></label>
            <input class="form-control" name="target_url" value="<?= htmlspecialchars((string) ($selectedPlan['target_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars($languageService->get('placeholder_url'), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="text-end"><button class="btn btn-primary"><?= $languageService->get('action_save') ?></button></div>
        </form>
      </div>
    <?php elseif ($action === 'features' && $selectedPlan !== null): ?>
      <div class="border rounded-3 p-3 p-lg-4 bg-light-subtle">
        <div class="mb-4">
          <h5 class="mb-1"><?= htmlspecialchars((string) ($selectedPlan['title_de'] ?? $selectedPlan['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h5>
          <p class="text-muted mb-0">ID #<?= (int) $selectedPlan['id'] ?></p>
        </div>
        <div class="border rounded-3 p-3 mb-4 bg-white">
          <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
            <div>
              <h6 class="mb-1"><?= $languageService->get('section_features') ?></h6>
              <p class="text-muted mb-0"><?= $languageService->get('info_features_grouping') ?></p>
            </div>
            <?= pricing_admin_lang_switcher('new-feature-' . (int) $selectedPlan['id'], $languages, $currentLanguage) ?>
          </div>
          <form method="post">
            <input type="hidden" name="save_feature" value="1"><input type="hidden" name="id" value="0"><input type="hidden" name="plan_id" value="<?= (int) $selectedPlan['id'] ?>">
            <?php foreach ($languages as $iso => $label): ?>
              <div class="pricing-lang-row" data-group="new-feature-<?= (int) $selectedPlan['id'] ?>" data-lang="<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>">
                <label class="form-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> <?= $languageService->get('label_text') ?></label>
                <input class="form-control" name="feature_text_<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>" <?= $iso === 'de' ? 'required' : '' ?>>
              </div>
            <?php endforeach; ?>
            <div class="row g-3 align-items-end mt-1">
              <div class="col-12 col-md-3"><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="available" value="1" id="avail_<?= (int) $selectedPlan['id'] ?>"><label class="form-check-label" for="avail_<?= (int) $selectedPlan['id'] ?>"><?= $languageService->get('label_available') ?></label></div></div>
              <div class="col-12 col-md-3 ms-md-auto"><button class="btn btn-primary w-100"><?= $languageService->get('add_feature') ?></button></div>
            </div>
          </form>
        </div>
        <?php foreach ($selectedFeatures as $feature): ?>
          <form method="post" class="border rounded-3 p-3 mb-3 bg-white">
            <input type="hidden" name="save_feature" value="1"><input type="hidden" name="id" value="<?= (int) $feature['id'] ?>"><input type="hidden" name="plan_id" value="<?= (int) $selectedPlan['id'] ?>">
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
              <h6 class="mb-0">Feature #<?= (int) $feature['id'] ?></h6>
              <?= pricing_admin_lang_switcher('feature-' . (int) $feature['id'], $languages, $currentLanguage) ?>
            </div>
            <?php foreach ($languages as $iso => $label): ?>
              <div class="pricing-lang-row" data-group="feature-<?= (int) $feature['id'] ?>" data-lang="<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>">
                <label class="form-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> <?= $languageService->get('label_text') ?></label>
                <input class="form-control" name="feature_text_<?= htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) ($feature['feature_text_' . $iso] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $iso === 'de' ? 'required' : '' ?>>
              </div>
            <?php endforeach; ?>
            <div class="row g-3 align-items-end mt-1">
              <div class="col-12 col-md-3"><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="available" value="1" id="feature_<?= (int) $feature['id'] ?>" <?= !empty($feature['available']) ? 'checked' : '' ?>><label class="form-check-label" for="feature_<?= (int) $feature['id'] ?>"><?= $languageService->get('label_available') ?></label></div></div>
              <div class="col-12 col-md-3 ms-md-auto d-flex gap-2"><button class="btn btn-primary flex-grow-1"><?= $languageService->get('action_save') ?></button><a href="#" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-confirm-url="<?= htmlspecialchars(pricing_admin_url('features', (int) $selectedPlan['id']) . '&delete_feature=' . (int) $feature['id'], ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-trash"></i></a></div>
            </div>
          </form>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
        <div>
          <h5 class="mb-1"><?= $languageService->get('title_overview') ?></h5>
          <p class="text-muted mb-0"><?= $languageService->get('info_admin_overview') ?></p>
        </div>
      </div>
      <?php if (empty($plans)): ?>
        <div class="alert alert-info mb-0"><?= $languageService->get('info_no_plans') ?></div>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($plans as $plan): ?>
            <div class="col-12">
              <div class="border rounded-3 p-3">
                <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                  <div>
                    <h6 class="mb-1"><?= htmlspecialchars((string) ($plan['title_de'] ?? $plan['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h6>
                    <div class="text-muted small">ID #<?= (int) $plan['id'] ?> | <?= $languageService->get('label_price') ?>: <?= htmlspecialchars((string) ($plan['price'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></div>
                  </div>
                  <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars(pricing_admin_url('edit', (int) $plan['id']), ENT_QUOTES, 'UTF-8') ?>"><?= $languageService->get('action_edit_plan') ?></a>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(pricing_admin_url('features', (int) $plan['id']), ENT_QUOTES, 'UTF-8') ?>"><?= $languageService->get('action_manage_features') ?></a>
                    <a href="#" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" data-confirm-url="<?= htmlspecialchars(pricing_admin_url() . '&delete_plan=' . (int) $plan['id'], ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-trash"></i></a>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<script>
(function () {
  const switchers = document.querySelectorAll('.pricing-lang-switch');
  if (!switchers.length) {
    return;
  }

  function setLang(group, lang) {
    document.querySelectorAll('.pricing-lang-row[data-group="' + group + '"]').forEach(function (row) {
      row.style.display = row.getAttribute('data-lang') === lang ? '' : 'none';
    });

    document.querySelectorAll('.pricing-lang-switch[data-group="' + group + '"] [data-lang]').forEach(function (button) {
      const active = button.getAttribute('data-lang') === lang;
      button.classList.toggle('active', active);
      button.classList.toggle('btn-primary', active);
      button.classList.toggle('btn-secondary', !active);
    });
  }

  switchers.forEach(function (switcher) {
    const group = switcher.getAttribute('data-group');
    const active = switcher.querySelector('[data-lang].active');
    const initialLang = active ? active.getAttribute('data-lang') : 'de';

    switcher.querySelectorAll('[data-lang]').forEach(function (button) {
      button.addEventListener('click', function () {
        setLang(group, button.getAttribute('data-lang'));
      });
    });

    setLang(group, initialLang);
  });
})();
</script>
