<?php

/**
 * One-off scaffolding helper for the thin Eloquent models (plain relations and
 * casts, no behaviour). Models that carry real domain logic are written by hand.
 *
 *     php database/generate_models.php
 */
$simple = [
    'UserProfile' => ['casts' => ['socials' => 'array', 'onboarding_state' => 'array'], 'belongsTo' => ['user' => 'User']],
    'Role' => ['belongsToMany' => ['users' => 'User', 'permissions' => 'Permission']],
    'Permission' => ['belongsToMany' => ['roles' => 'Role']],
    'StoreDomain' => ['casts' => ['verified_at' => 'datetime', 'last_checked_at' => 'datetime', 'is_primary' => 'boolean'], 'belongsTo' => ['store' => 'Store']],
    'StoreTheme' => ['casts' => ['extras' => 'array'], 'belongsTo' => ['store' => 'Store']],
    'BlockVersion' => ['casts' => ['snapshot' => 'array'], 'belongsTo' => ['block' => 'Block', 'user' => 'User']],
    'ProductCategory' => ['casts' => ['is_active' => 'boolean'], 'hasMany' => ['products' => 'Product']],
    'ProductMedia' => ['belongsTo' => ['product' => 'Product']],
    'ProductFile' => ['casts' => ['watermark_pdf' => 'boolean'], 'belongsTo' => ['product' => 'Product']],
    'CourseSection' => ['belongsTo' => ['course' => 'Course'], 'hasMany' => ['lessons' => 'Lesson']],
    'LessonProgress' => ['table' => 'lesson_progress', 'casts' => ['completed' => 'boolean', 'completed_at' => 'datetime'], 'belongsTo' => ['enrollment' => 'Enrollment', 'lesson' => 'Lesson']],
    'Ticket' => ['casts' => ['price' => 'decimal:2', 'is_active' => 'boolean'], 'belongsTo' => ['event' => 'Event']],
    'Attendee' => ['casts' => ['checked_in_at' => 'datetime'], 'belongsTo' => ['event' => 'Event', 'ticket' => 'Ticket', 'order' => 'Order']],
    'AvailabilityRule' => ['casts' => ['is_active' => 'boolean'], 'belongsTo' => ['service' => 'Service']],
    'MembershipPlan' => ['casts' => ['price' => 'decimal:2', 'benefits' => 'array', 'is_active' => 'boolean'], 'belongsTo' => ['product' => 'Product'], 'hasMany' => ['memberships' => 'Membership']],
    'CustomerAddress' => ['casts' => ['is_default' => 'boolean'], 'belongsTo' => ['customer' => 'Customer']],
    'CartItem' => ['casts' => ['unit_price' => 'decimal:2', 'meta' => 'array'], 'belongsTo' => ['cart' => 'Cart', 'product' => 'Product', 'variant' => 'ProductVariant:product_variant_id']],
    'CouponUsage' => ['casts' => ['amount' => 'decimal:2'], 'belongsTo' => ['coupon' => 'Coupon', 'order' => 'Order', 'customer' => 'Customer']],
    'PaymentAttempt' => ['casts' => ['request' => 'array', 'response' => 'array'], 'belongsTo' => ['payment' => 'Payment']],
    'PaymentWebhook' => ['casts' => ['headers' => 'array', 'signature_valid' => 'boolean', 'processed' => 'boolean', 'processed_at' => 'datetime']],
    'Download' => ['belongsTo' => ['access' => 'DigitalAccess:digital_access_id']],
    'AffiliateApplication' => ['casts' => ['reviewed_at' => 'datetime'], 'belongsTo' => ['program' => 'AffiliateProgram:affiliate_program_id', 'user' => 'User']],
    'AffiliateClick' => ['casts' => ['utm' => 'array', 'expires_at' => 'datetime', 'converted' => 'boolean'], 'belongsTo' => ['link' => 'AffiliateLink:affiliate_link_id']],
    'LedgerAccount' => [],
    'PlanFeature' => ['casts' => ['enabled' => 'boolean'], 'belongsTo' => ['plan' => 'Plan']],
    'SubscriptionInvoice' => ['casts' => ['amount' => 'decimal:2', 'period_start' => 'datetime', 'period_end' => 'datetime', 'paid_at' => 'datetime'], 'belongsTo' => ['subscription' => 'Subscription']],
    'PlanUsage' => ['belongsTo' => ['user' => 'User']],
    'FeatureFlag' => ['casts' => ['enabled' => 'boolean', 'audience' => 'array']],
    'Lead' => ['casts' => ['fields' => 'array', 'consent' => 'boolean', 'utm' => 'array'], 'belongsTo' => ['store' => 'Store', 'block' => 'Block']],
    'MarketingConsent' => ['casts' => ['subscribed' => 'boolean', 'subscribed_at' => 'datetime', 'unsubscribed_at' => 'datetime'], 'belongsTo' => ['store' => 'Store']],
    'Campaign' => ['casts' => ['segment_config' => 'array', 'scheduled_at' => 'datetime', 'sent_at' => 'datetime'], 'belongsTo' => ['store' => 'Store']],
    'IntegrationSetting' => ['casts' => ['credentials' => 'encrypted', 'config' => 'array', 'is_active' => 'boolean'], 'belongsTo' => ['store' => 'Store']],
    'WebhookDelivery' => ['casts' => ['delivered_at' => 'datetime'], 'belongsTo' => ['endpoint' => 'WebhookEndpoint:webhook_endpoint_id']],
    'AnalyticsSummary' => ['casts' => ['date' => 'date', 'sources' => 'array', 'gross_revenue' => 'decimal:2', 'net_revenue' => 'decimal:2'], 'belongsTo' => ['store' => 'Store']],
    'SupportMessage' => ['casts' => ['attachments' => 'array', 'is_internal_note' => 'boolean'], 'belongsTo' => ['ticket' => 'SupportTicket:support_ticket_id', 'user' => 'User']],
    'ContentReport' => ['belongsTo' => ['reporter' => 'User:reporter_id', 'reviewer' => 'User:reviewed_by']],
    'StaticPage' => ['casts' => ['is_published' => 'boolean']],
    'Refund' => ['casts' => ['amount' => 'decimal:2', 'processed_at' => 'datetime'], 'belongsTo' => ['order' => 'Order', 'payment' => 'Payment', 'requester' => 'User:requested_by', 'processor' => 'User:processed_by']],
    'StockMovement' => ['belongsTo' => ['inventory' => 'Inventory', 'user' => 'User']],
];

$dir = dirname(__DIR__).'/app/Models';

foreach ($simple as $class => $cfg) {
    $uses = ['use Illuminate\Database\Eloquent\Model;'];
    $body = '';

    if (! empty($cfg['table'])) {
        $body .= "    protected \$table = '{$cfg['table']}';\n\n";
    }

    $body .= "    protected \$guarded = [];\n";

    if (! empty($cfg['casts'])) {
        $body .= "\n    protected function casts(): array\n    {\n        return [\n";
        foreach ($cfg['casts'] as $k => $v) {
            $body .= "            '{$k}' => '{$v}',\n";
        }
        $body .= "        ];\n    }\n";
    }

    foreach (($cfg['belongsTo'] ?? []) as $rel => $target) {
        [$model, $fk] = array_pad(explode(':', $target), 2, null);
        $uses[] = 'use Illuminate\Database\Eloquent\Relations\BelongsTo;';
        $args = $fk ? ", '{$fk}'" : '';
        $body .= "\n    public function {$rel}(): BelongsTo\n    {\n        return \$this->belongsTo({$model}::class{$args});\n    }\n";
    }

    foreach (($cfg['hasMany'] ?? []) as $rel => $model) {
        $uses[] = 'use Illuminate\Database\Eloquent\Relations\HasMany;';
        $body .= "\n    public function {$rel}(): HasMany\n    {\n        return \$this->hasMany({$model}::class);\n    }\n";
    }

    foreach (($cfg['belongsToMany'] ?? []) as $rel => $model) {
        $uses[] = 'use Illuminate\Database\Eloquent\Relations\BelongsToMany;';
        $body .= "\n    public function {$rel}(): BelongsToMany\n    {\n        return \$this->belongsToMany({$model}::class);\n    }\n";
    }

    $uses = array_values(array_unique($uses));
    sort($uses);

    $php = "<?php\n\nnamespace App\Models;\n\n".implode("\n", $uses)."\n\nclass {$class} extends Model\n{\n".$body."}\n";
    file_put_contents("{$dir}/{$class}.php", $php);
}

echo 'Generated '.count($simple)." models.\n";
