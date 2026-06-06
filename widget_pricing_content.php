<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\SeoUrlHandler;

global $_database, $languageService;

$languageService->readPluginModule('pricing');

if (!defined('PRICING_WIDGET_CONTENT_CSS_LOADED')) {
    define('PRICING_WIDGET_CONTENT_CSS_LOADED', true);
    $pricingWidgetCssPath = __DIR__ . '/css/widget_pricing_content.css';
    $pricingWidgetCssVersion = file_exists($pricingWidgetCssPath) ? filemtime($pricingWidgetCssPath) : time();
    echo '<link rel="stylesheet" href="/includes/plugins/pricing/css/widget_pricing_content.css?v='
        . (int)$pricingWidgetCssVersion . '">' . PHP_EOL;
}

if (!function_exists('pricing_widget_current_language')) {
    function pricing_widget_current_language(LanguageService $languageService): string
    {
        $lang = strtolower((string)$languageService->detectLanguage());
        return in_array($lang, ['de', 'en', 'it'], true) ? $lang : 'en';
    }
}

if (!function_exists('pricing_widget_localized_value')) {
    function pricing_widget_localized_value(array $row, string $baseKey, string $lang): string
    {
        $candidates = [$baseKey . '_' . $lang, $baseKey . '_en', $baseKey . '_de', $baseKey . '_it', $baseKey];
        foreach ($candidates as $candidate) {
            $value = trim((string)($row[$candidate] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

if (!function_exists('pricing_widget_build_plan_url')) {
    function pricing_widget_build_plan_url(int $planId): string
    {
        return SeoUrlHandler::convertToSeoUrl('index.php?site=pricing&plan=' . $planId);
    }
}

$currentLang = pricing_widget_current_language($languageService);

$plans = [];
$res = $_database->query("SELECT * FROM plugins_pricing_plans ORDER BY sort_order ASC, id ASC");
while ($res && ($plan = $res->fetch_assoc())) {
    $planId = (int)($plan['id'] ?? 0);
    $plans[$planId] = $plan;
    $plans[$planId]['features'] = [];
}

$res2 = $_database->query("SELECT * FROM plugins_pricing_features ORDER BY plan_id ASC, id ASC");
while ($res2 && ($feature = $res2->fetch_assoc())) {
    $planId = (int)($feature['plan_id'] ?? 0);
    if (isset($plans[$planId])) {
        $plans[$planId]['features'][] = $feature;
    }
}
?>

<section class="pricing-widget-content my-4" aria-labelledby="pricing-widget-content-title">
  <div class="pricing-widget-content__head">
    <div>
      <p class="pricing-widget-content__kicker mb-1">Pricing</p>
      <h4 id="pricing-widget-content-title" class="pricing-widget-content__title mb-0">
        <i class="bi bi-tags" aria-hidden="true"></i>
        <?php echo htmlspecialchars($languageService->get('title_pricing'), ENT_QUOTES, 'UTF-8'); ?>
      </h4>
    </div>
    <span class="pricing-widget-content__count"><?php echo count($plans); ?> Tarife</span>
  </div>

  <?php if (empty($plans)): ?>
    <div class="pricing-widget-content__empty">
      <?php echo htmlspecialchars($languageService->get('no_pricing'), ENT_QUOTES, 'UTF-8'); ?>
    </div>
  <?php else: ?>
    <div class="pricing-widget-content__grid">
      <?php foreach ($plans as $plan):
          $planId = (int)($plan['id'] ?? 0);
          $title = pricing_widget_localized_value($plan, 'title', $currentLang);
          $unit = pricing_widget_localized_value($plan, 'price_unit', $currentLang);
          $price = trim((string)($plan['price'] ?? '0'));
          $detailUrl = pricing_widget_build_plan_url($planId);
          $features = [];
          foreach (($plan['features'] ?? []) as $feature) {
              if ((int)($feature['available'] ?? 0) !== 1) {
                  continue;
              }
              $featureText = pricing_widget_localized_value($feature, 'feature_text', $currentLang);
              if ($featureText !== '') {
                  $features[] = $featureText;
              }
          }
          $featurePreview = array_slice($features, 0, 4);
      ?>
        <a href="<?php echo htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8'); ?>"
           class="pricing-widget-content__card<?php echo !empty($plan['is_featured']) ? ' is-featured' : ''; ?>"
           title="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
          <?php if (!empty($plan['is_advanced'])): ?>
            <span class="pricing-widget-content__badge">
              <?php echo htmlspecialchars($languageService->get('feature_advanced'), ENT_QUOTES, 'UTF-8'); ?>
            </span>
          <?php endif; ?>

          <h5 class="pricing-widget-content__plan"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h5>

          <div class="pricing-widget-content__price">
            <span class="pricing-widget-content__currency">EUR</span>
            <strong><?php echo htmlspecialchars($price, ENT_QUOTES, 'UTF-8'); ?></strong>
            <span class="pricing-widget-content__unit"><?php echo htmlspecialchars($unit, ENT_QUOTES, 'UTF-8'); ?></span>
          </div>

          <?php if (!empty($featurePreview)): ?>
            <ul class="pricing-widget-content__features">
              <?php foreach ($featurePreview as $featureText): ?>
                <li><?php echo htmlspecialchars($featureText, ENT_QUOTES, 'UTF-8'); ?></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="pricing-widget-content__note">
              <?php echo htmlspecialchars($languageService->get('info_no_features'), ENT_QUOTES, 'UTF-8'); ?>
            </p>
          <?php endif; ?>

          <span class="pricing-widget-content__read">
            <?php echo htmlspecialchars($languageService->get('btn_view_details'), ENT_QUOTES, 'UTF-8'); ?>
            <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
