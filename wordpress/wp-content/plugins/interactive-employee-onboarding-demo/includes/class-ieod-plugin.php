<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class IEOD_Plugin
{
    private static ?self $instance = null;

    /** @var array<string, string> */
    private array $pages = [
        'access' => 'Demo Access',
        'onboarding' => 'Interactive Onboarding',
        'location-windows' => 'Office or Remote',
        'location-mac' => 'Office or Remote',
        'office-selection-windows' => 'Office Selection',
        'office-selection-mac' => 'Office Selection',
        'password-change-windows' => 'Password Change',
        'password-change-mac' => 'Password Change',
        '2fa-windows' => 'Two-Factor Authentication',
        '2fa-mac' => 'Two-Factor Authentication',
        'vpn-windows' => 'VPN Configuration',
        'vpn-mac' => 'VPN Configuration',
        'signature-windows' => 'Email Signature',
        'signature-mac' => 'Email Signature',
        'company-portal' => 'Company Portal',
        'self-service' => 'Self Service',
        'toolbox' => 'Toolbox',
        'it-policy' => 'IT Policy',
        'phishing' => 'Phishing Awareness',
        'it-contact' => 'IT Contact',
        'guest-user' => 'Guest Account',
        'verify-email-code' => 'Verify Email Code',
        'accept-permissions' => 'Accept Permissions',
        '2fa-guest' => 'Guest Two-Factor Authentication',
    ];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        add_action('init', [$this, 'register_shortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('template_redirect', [$this, 'protect_pages'], 1);
        add_filter('template_include', [$this, 'template_include'], 99);
        add_filter('show_admin_bar', fn(bool $show): bool => $this->is_demo_page() ? false : $show);
        add_filter('body_class', function (array $classes): array {
            if ($this->is_demo_page()) {
                $classes[] = 'ieod-site';
                $classes[] = 'ieod-page-' . sanitize_html_class($this->slug());
            }
            return $classes;
        });
    }

    public static function activate(): void
    {
        $plugin = self::instance();
        foreach ($plugin->pages as $slug => $title) {
            if (get_page_by_path($slug) instanceof WP_Post) {
                continue;
            }
            wp_insert_post([
                'post_title' => $title,
                'post_name' => $slug,
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed',
                'post_content' => $slug === 'access' ? '[ieod_gate]' : sprintf('[ieod_step id="%s"]', esc_attr($slug)),
            ]);
        }
        update_option('show_on_front', 'page');
        $access = get_page_by_path('access');
        if ($access instanceof WP_Post) {
            update_option('page_on_front', $access->ID);
        }
        update_option('permalink_structure', '/%postname%/');
        flush_rewrite_rules();
    }

    public function register_shortcodes(): void
    {
        add_shortcode('ieod_gate', [$this, 'render_gate']);
        add_shortcode('ieod_step', [$this, 'render_step']);
    }

    public function enqueue_assets(): void
    {
        if (!$this->is_demo_page()) {
            return;
        }
        wp_enqueue_style('ieod', IEOD_URL . 'assets/css/onboarding.css', [], IEOD_VERSION);
        wp_enqueue_script('ieod', IEOD_URL . 'assets/js/onboarding.js', [], IEOD_VERSION, true);

        $routes = [];
        foreach (array_keys($this->pages) as $slug) {
            $routes[$slug] = $this->route($slug);
        }
        wp_localize_script('ieod', 'IEOD_CONFIG', [
            'routes' => $routes,
            'currentSlug' => $this->slug(),
            'flow' => $this->flow(),
            'storagePrefix' => 'ieod_demo_v121_',
        ]);
    }

    public function template_include(string $template): string
    {
        return $this->is_demo_page() ? IEOD_DIR . 'templates/blank.php' : $template;
    }

    public function protect_pages(): void
    {
        if (!$this->is_demo_page()) {
            return;
        }
        if (isset($_GET['ieod_logout'])) {
            $this->clear_cookies();
            wp_safe_redirect($this->route('access'));
            exit;
        }
        if ($this->slug() === 'access') {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ieod_gate_action'])) {
                $this->process_gate();
            }
            return;
        }
        if (current_user_can('manage_options')) {
            return;
        }
        if (!$this->authorized()) {
            wp_safe_redirect($this->route('access'));
            exit;
        }
        $guest = ['guest-user', 'verify-email-code', 'accept-permissions', '2fa-guest'];
        if ($this->flow() === 'guest' && !in_array($this->slug(), $guest, true)) {
            wp_safe_redirect($this->route('guest-user'));
            exit;
        }
        if ($this->flow() === 'internal' && in_array($this->slug(), $guest, true)) {
            wp_safe_redirect($this->route('onboarding'));
            exit;
        }
    }

    private function process_gate(): void
    {
        $nonce = sanitize_text_field(wp_unslash($_POST['_ieod_nonce'] ?? ''));
        $password = (string) wp_unslash($_POST['ieod_password'] ?? '');
        $flow = null;
        if (hash_equals($this->employee_password(), $password)) {
            $flow = 'internal';
        } elseif (hash_equals($this->guest_password(), $password)) {
            $flow = 'guest';
        }
        if (!wp_verify_nonce($nonce, 'ieod_gate') || $flow === null) {
            wp_safe_redirect(add_query_arg('gate_error', '1', $this->route('access')));
            exit;
        }
        $this->set_cookies($flow);
        wp_safe_redirect($this->route($flow === 'guest' ? 'guest-user' : 'onboarding'));
        exit;
    }

    public function render_gate(): string
    {
        if ($this->authorized()) {
            $target = $this->flow() === 'guest' ? 'guest-user' : 'onboarding';
            return sprintf(
                '<main class="ieod-gate"><div class="ieod-gate-brand"><span>O</span> ONBOARD</div><section class="ieod-gate__card"><h1>Access granted</h1><p>Your local demo session is active.</p><div class="ieod-gate__actions"><a class="ieod-btn ieod-btn--primary" href="%s">Continue</a><a class="ieod-btn" href="%s">Lock again</a></div></section></main>',
                esc_url($this->route($target)),
                esc_url(add_query_arg('ieod_logout', '1', $this->route('access')))
            );
        }
        ob_start(); ?>
        <main class="ieod-gate">
            <div class="ieod-gate-brand"><span>O</span> ONBOARD</div>
            <section class="ieod-gate__card">
                <p class="ieod-eyebrow">PRIVATE DEMO</p>
                <h1>Self-Guided Onboarding</h1>
                <p>Enter a demo password to open the employee or guest flow.</p>
                <?php if (isset($_GET['gate_error'])) : ?><div class="ieod-alert" role="alert">Wrong password. Please try again.</div><?php endif; ?>
                <form method="post" class="ieod-gate__form">
                    <?php wp_nonce_field('ieod_gate', '_ieod_nonce'); ?>
                    <input type="hidden" name="ieod_gate_action" value="unlock">
                    <label for="ieod_password">Password</label>
                    <input id="ieod_password" name="ieod_password" type="password" autocomplete="current-password" required>
                    <button class="ieod-btn ieod-btn--primary" type="submit">Unlock</button>
                </form>
                <details><summary>Demo access</summary><p><code>demo123</code> · employee &nbsp; <code>guest123</code> · guest</p></details>
            </section>
        </main>
        <?php return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $atts */
    public function render_step(array $atts): string
    {
        $id = sanitize_key((string) ($atts['id'] ?? $this->slug()));
        if ($id === 'onboarding') {
            return $this->shell($id, '<p class="ieod-eyebrow">WELCOME</p><h1>IT-Onboarding</h1><p>Choose the device that you want to configure. Your selection determines the remaining route.</p><section class="ieod-question-card"><h2>What do you need to set up?</h2><div class="ieod-choice-grid"><button class="ieod-choice-card" data-ieod-os="mac" data-ieod-go="location-mac"><strong>I have a Mac</strong><span>macOS setup flow</span></button><button class="ieod-choice-card" data-ieod-os="windows" data-ieod-go="location-windows"><strong>I have a Windows PC</strong><span>Windows setup flow</span></button></div></section>');
        }
        if (str_starts_with($id, 'location-')) {
            $os = str_ends_with($id, 'mac') ? 'mac' : 'windows';
            return $this->shell($id, '<p class="ieod-eyebrow">WORKPLACE</p><h1>Office or Remote?</h1><p>The route changes depending on whether secure remote access is needed immediately.</p><div class="ieod-choice-grid"><button class="ieod-choice-card" data-ieod-location="office" data-ieod-go="office-selection-' . esc_attr($os) . '"><strong>Office</strong><span>Select a fictional location</span></button><button class="ieod-choice-card" data-ieod-location="remote" data-ieod-go="password-change-' . esc_attr($os) . '"><strong>Remote</strong><span>VPN appears earlier</span></button></div>');
        }
        if (str_starts_with($id, 'office-selection-')) {
            $os = str_ends_with($id, 'mac') ? 'mac' : 'windows';
            $buttons = '';
            foreach (['North Campus', 'Riverside Office', 'Central Hub', 'Remote-style Branch'] as $office) {
                $buttons .= '<button class="ieod-office-card" data-ieod-office="' . esc_attr($office) . '" data-ieod-go="password-change-' . esc_attr($os) . '"><strong>' . esc_html($office) . '</strong><span>Fictional demo location</span></button>';
            }
            return $this->shell($id, '<p class="ieod-eyebrow">LOCATION</p><h1>Select your office</h1><p>Office names and network details are fictional.</p><div class="ieod-office-grid">' . $buttons . '</div>');
        }

        $content = $this->content($id);
        $cards = '';
        foreach ($content['cards'] as $index => $card) {
            $items = '';
            foreach ($card['items'] as $item) {
                $items .= '<li>' . esc_html($item) . '</li>';
            }
            $cards .= '<article class="ieod-step-card"><div class="ieod-step-card__number">' . ($index + 1) . '</div><div><h2>' . esc_html($card['title']) . '</h2><p>' . esc_html($card['text']) . '</p><ol>' . $items . '</ol></div></article>';
        }
        return $this->shell($id, '<p class="ieod-eyebrow">' . esc_html($content['eyebrow']) . '</p><h1>' . esc_html($content['title']) . '</h1><p>' . esc_html($content['intro']) . '</p><div class="ieod-demo-notice"><strong>Public demo:</strong> real links, media and account actions are intentionally replaced.</div><div class="ieod-step-list">' . $cards . '</div>');
    }

    private function shell(string $id, string $content): string
    {
        $flow = $this->flow();
        $steps = $flow === 'guest'
            ? ['guest-user' => 'Guest account', 'verify-email-code' => 'Email code', 'accept-permissions' => 'Permissions', '2fa-guest' => 'Two-factor authentication']
            : ['onboarding' => 'Device selection', 'location' => 'Office / Remote', 'password' => 'Password change', '2fa' => 'Two-factor authentication', 'vpn' => 'VPN configuration', 'signature' => 'Email signature', 'company-portal' => 'Managed apps', 'toolbox' => 'Workplace tools', 'it-policy' => 'IT policy', 'phishing' => 'Phishing awareness', 'it-contact' => 'IT contact'];
        $nav = '';
        foreach ($steps as $key => $label) {
            $active = str_contains($id, $key) || $id === $key;
            $nav .= '<li><span class="' . ($active ? 'is-current' : '') . '">' . esc_html($label) . '</span></li>';
        }
        ob_start(); ?>
        <div class="ieod-app" data-ieod-step="<?php echo esc_attr($id); ?>">
            <header class="ieod-header"><a class="ieod-brand" href="<?php echo esc_url($this->route($flow === 'guest' ? 'guest-user' : 'onboarding')); ?>"><span>O</span> ONBOARD</a><div><button class="ieod-icon-btn" type="button" data-ieod-tutorial>Tour</button><button class="ieod-theme-switch" type="button" data-ieod-theme-toggle aria-label="Toggle color theme">◐</button></div></header>
            <div class="ieod-shell"><aside class="ieod-sidebar"><p>Flow</p><ol><?php echo $nav; ?></ol></aside><main class="ieod-content"><?php echo $content; ?></main></div>
            <footer class="ieod-bottom-bar"><button class="ieod-btn" type="button" data-ieod-back>Back</button><div><a class="ieod-btn" href="<?php echo esc_url(add_query_arg('ieod_logout', '1', $this->route('access'))); ?>">Lock demo</a><button class="ieod-btn" type="button" data-ieod-scope>Demo scope</button></div><button class="ieod-btn ieod-btn--primary" type="button" data-ieod-next>Next</button></footer>
        </div>
        <?php return (string) ob_get_clean();
    }

    /** @return array{eyebrow:string,title:string,intro:string,cards:array<int,array{title:string,text:string,items:array<int,string>}>} */
    private function content(string $id): array
    {
        $map = [
            'password-change' => ['ACCOUNT SECURITY', 'Change your temporary password', 'Create a strong personal password before using company services.', ['Open the fictional identity portal', 'Create a unique password with at least 14 characters', 'Reconnect mail and collaboration applications']],
            '2fa' => ['ACCOUNT SECURITY', 'Set up two-factor authentication', 'Connect an authenticator app and verify a test prompt.', ['Open the fictional security-information page', 'Scan the non-functional demo QR illustration', 'Approve only requests you initiated']],
            'vpn' => ['SECURE ACCESS', 'Configure remote access', 'The production guide linked to approved VPN profiles. This demo performs no provisioning.', ['Open the fictional VPN portal', 'Choose the correct operating system', 'Connect and verify the status indicator']],
            'signature' => ['COMMUNICATION', 'Create your email signature', 'Generate a consistent signature and configure it in the mail client.', ['Open the fictional signature generator', 'Copy the formatted preview', 'Set it for new messages and replies']],
            'company-portal' => ['MANAGED SOFTWARE', 'Install approved applications', 'Browse a fictional managed catalog without triggering device-management actions.', ['Open the managed-app catalog', 'Search for an approved application', 'Review installation status']],
            'self-service' => ['MANAGED SOFTWARE', 'Use self service', 'Install approved macOS software through a fictional catalog.', ['Open Self Service', 'Choose an approved application', 'Confirm the local demo status']],
            'toolbox' => ['WORKPLACE TOOLS', 'Explore workplace tools', 'Review fictional examples of collaboration, knowledge and service tools.', ['Open the collaboration workspace', 'Find the knowledge base', 'Review the service portal']],
            'it-policy' => ['SECURITY', 'Read the IT policy', 'Understand acceptable use, data handling and device responsibilities.', ['Review acceptable use', 'Protect confidential information', 'Report lost devices immediately']],
            'phishing' => ['SECURITY', 'Recognize phishing attempts', 'Practice checking sender, link and urgency signals.', ['Inspect the sender address', 'Preview links before opening', 'Report suspicious messages']],
            'it-contact' => ['SUPPORT', 'Know how to reach IT', 'All contact details are fictional placeholders in the public demo.', ['Search the knowledge base first', 'Create a service request', 'Use the emergency path only for urgent incidents']],
            'guest-user' => ['GUEST COLLABORATION', 'Open the guest invitation', 'Start with the external address that received the fictional invitation.', ['Open the invitation in a supported browser', 'Choose the invited account', 'Continue to email verification']],
            'verify-email-code' => ['GUEST COLLABORATION', 'Verify your email code', 'No email is sent. The page demonstrates the intended user journey.', ['Request the fictional one-time code', 'Open the external mailbox', 'Enter the demo code']],
            'accept-permissions' => ['GUEST COLLABORATION', 'Review requested permissions', 'Confirm the fictional organization and requested access.', ['Read the organization notice', 'Review the minimum permissions', 'Accept only expected requests']],
        ];
        $key = 'toolbox';
        foreach (array_keys($map) as $candidate) {
            if (str_contains($id, $candidate)) {
                $key = $candidate;
                break;
            }
        }
        [$eyebrow, $title, $intro, $items] = $map[$key];
        $cards = [];
        foreach ($items as $index => $item) {
            $cards[] = ['title' => 'Step ' . ($index + 1), 'text' => $item, 'items' => ['Follow the fictional on-screen guidance.', 'Do not enter real credentials into this public demo.', 'Continue when the example status is complete.']];
        }
        return compact('eyebrow', 'title', 'intro', 'cards');
    }

    private function is_demo_page(): bool
    {
        return is_page() && array_key_exists($this->slug(), $this->pages);
    }

    private function slug(): string
    {
        $post = get_post();
        return $post instanceof WP_Post ? (string) $post->post_name : '';
    }

    private function route(string $slug): string
    {
        $page = get_page_by_path($slug);
        return $page instanceof WP_Post ? get_permalink($page) : home_url('/' . trim($slug, '/') . '/');
    }

    private function employee_password(): string
    {
        $value = getenv('ONBOARDING_DEMO_PASSWORD');
        return is_string($value) && $value !== '' ? $value : 'demo123';
    }

    private function guest_password(): string
    {
        $value = getenv('ONBOARDING_GUEST_PASSWORD');
        return is_string($value) && $value !== '' ? $value : 'guest123';
    }

    private function token(): string
    {
        return hash_hmac('sha256', 'ieod-demo-access-v121', wp_salt('auth'));
    }

    private function authorized(): bool
    {
        $cookie = (string) ($_COOKIE['ieod_demo_access'] ?? '');
        return $cookie !== '' && hash_equals($this->token(), $cookie);
    }

    private function flow(): string
    {
        $flow = sanitize_key((string) ($_COOKIE['ieod_demo_flow'] ?? 'internal'));
        return in_array($flow, ['internal', 'guest'], true) ? $flow : 'internal';
    }

    private function set_cookies(string $flow): void
    {
        $options = ['expires' => time() + DAY_IN_SECONDS, 'path' => COOKIEPATH ?: '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax'];
        setcookie('ieod_demo_access', $this->token(), $options);
        setcookie('ieod_demo_flow', $flow, $options);
    }

    private function clear_cookies(): void
    {
        $options = ['expires' => time() - HOUR_IN_SECONDS, 'path' => COOKIEPATH ?: '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax'];
        setcookie('ieod_demo_access', '', $options);
        setcookie('ieod_demo_flow', '', $options);
    }
}
