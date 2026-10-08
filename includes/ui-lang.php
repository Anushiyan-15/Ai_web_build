<?php
// ═══════════════════════════════════════════════════════════════
//  includes/ui-lang.php — App UI localization (EN / TA / SI)
//
//  Customer language drives Portal + Builder chrome: buttons, forms,
//  templates, steps. English fallback for any missing key.
//  Preference: ?lang=xx → session → customer row → 'en'.
// ═══════════════════════════════════════════════════════════════

function ui_lang_list(): array {
    return [
        'en' => 'English',
        'ta' => 'தமிழ்',
        'si' => 'සිංහල',
    ];
}

function ui_lang_valid(string $l): bool {
    return isset(ui_lang_list()[$l]);
}

/**
 * Resolve the current UI language. ?lang=xx overrides + persists to
 * session (and account when logged in).
 */
function ui_resolve_lang(): string {
    if (session_status() === PHP_SESSION_NONE) @session_start();
    // NOTE: guests use $_SESSION['ui_lang'] only — never fabricate a
    // customer_user row (the portal treats a non-empty one as logged in).
    if (isset($_GET['lang']) && ui_lang_valid($_GET['lang'])) {
        $_SESSION['ui_lang'] = $_GET['lang'];
        if (!empty($_SESSION['customer_user']['email'])) {
            $_SESSION['customer_user']['language'] = $_GET['lang'];
            if (function_exists('setCustomerLanguage')) {
                try { setCustomerLanguage($_SESSION['customer_user']['email'], $_GET['lang']); } catch (Throwable $e) {}
            }
        }
        return $_GET['lang'];
    }
    $s = $_SESSION['customer_user']['language'] ?? ($_SESSION['ui_lang'] ?? '');
    if (ui_lang_valid($s)) return $s;
    $em = $_SESSION['customer_user']['email'] ?? '';
    if ($em !== '' && function_exists('getCustomerLanguage')) {
        try {
            $db = getCustomerLanguage($em);
            if (ui_lang_valid($db)) {
                $_SESSION['customer_user']['language'] = $db;
                return $db;
            }
        } catch (Throwable $e) {}
    }
    return 'en';
}

function ui_dict(): array {
    return [
        // ── Portal ──
        'p_logout' => ['en' => 'Logout', 'ta' => 'வெளியேறு', 'si' => 'ඉවත් වන්න'],
        'p_edit_profile' => ['en' => '✎ Edit Profile', 'ta' => '✎ சுயவிவரம்', 'si' => '✎ පැතිකඩ සංස්කරණය'],
        'p_your_name' => ['en' => 'Your Name', 'ta' => 'உங்கள் பெயர்', 'si' => 'ඔබේ නම'],
        'p_phone' => ['en' => 'Phone Number', 'ta' => 'தொலைபேசி', 'si' => 'දුරකථන අංකය'],
        'p_save' => ['en' => 'Save Changes', 'ta' => 'சேமி', 'si' => 'සුරකින්න'],
        'p_login_title' => ['en' => 'Customer Portal', 'ta' => 'வாடிக்கையாளர் போர்டல்', 'si' => 'පාරිභෝගික ද්වාරය'],
        'p_login_sub' => ['en' => 'Sign in or create a free account to manage your websites.', 'ta' => 'உங்கள் வலைத்தளங்களை நிர்வகிக்க உள்நுழையுங்கள் அல்லது இலவச கணக்கை உருவாக்குங்கள்.', 'si' => 'ඔබේ වෙබ් අඩවි කළමනාකරණයට පිවිසෙන්න හෝ නොමිලේ ගිණුමක් සාදන්න.'],
        'p_tab_signin' => ['en' => 'Sign In', 'ta' => 'உள்நுழை', 'si' => 'පිවිසෙන්න'],
        'p_tab_signup' => ['en' => 'Create Account', 'ta' => 'கணக்கு உருவாக்கு', 'si' => 'ගිණුම සාදන්න'],
        'p_tab_forgot' => ['en' => 'Forgot Password', 'ta' => 'கடவுச்சொல் மறந்துவிட்டதா', 'si' => 'මුරපදය අමතකද'],
        'p_fld_email' => ['en' => 'Email Address', 'ta' => 'மின்னஞ்சல் முகவரி', 'si' => 'විද්‍යුත් තැපෑල'],
        'p_fld_password' => ['en' => 'Password', 'ta' => 'கடவுச்சொல்', 'si' => 'මුරපදය'],
        'p_btn_signin' => ['en' => 'Sign In', 'ta' => 'உள்நுழை', 'si' => 'පිවිසෙන්න'],
        'p_dash_title' => ['en' => 'My Websites & Projects 👋', 'ta' => 'எனது வலைத்தளங்கள் 👋', 'si' => 'මගේ වෙබ් අඩවි 👋'],
        'p_dash_sub' => ['en' => 'Welcome back! Manage your active web properties, open CMS admin panels, and add AI features.', 'ta' => 'மீண்டும் வருக! உங்கள் வலைத்தளங்களை நிர்வகியுங்கள்.', 'si' => 'ආයුබෝවන්! ඔබේ වෙබ් අඩවි කළමනාකරණය කරන්න.'],
        'p_btn_create' => ['en' => '➕ Create New Website', 'ta' => '➕ புதிய வலைத்தளம்', 'si' => '➕ අලුත් වෙබ් අඩවියක්'],
        'p_stat_total' => ['en' => 'Total Websites', 'ta' => 'மொத்த வலைத்தளங்கள்', 'si' => 'මුළු අඩවි'],
        'p_stat_total_sub' => ['en' => 'Owned under your account', 'ta' => 'உங்கள் கணக்கில் உள்ளவை', 'si' => 'ඔබේ ගිණුමේ ඇති'],
        'p_stat_active' => ['en' => 'Active Online', 'ta' => 'நேரலையில்', 'si' => 'සක්‍රීයයි'],
        'p_stat_active_sub' => ['en' => 'Hosted & live right now', 'ta' => 'இப்போது நேரலையில்', 'si' => 'දැන් සජීවීව'],
        'p_stat_renewal' => ['en' => 'Next Renewal Due', 'ta' => 'அடுத்த புதுப்பிப்பு', 'si' => 'මීළඟ අලුත් කිරීම'],
        'p_stat_renewal_sub' => ['en' => 'Subscription auto-billing', 'ta' => 'தானியங்கு கட்டணம்', 'si' => 'ස්වයංක්‍රීය ගෙවීම'],
        'p_stat_notifs' => ['en' => 'Notifications', 'ta' => 'அறிவிப்புகள்', 'si' => 'දැනුම්දීම්'],
        'p_stat_notifs_sub' => ['en' => 'Updates & payment reminders', 'ta' => 'புதுப்பிப்புகள் & நினைவூட்டல்கள்', 'si' => 'යාවත්කාලීන හා මතක් කිරීම්'],
        'p_tab_sites' => ['en' => '🌐 Published Websites', 'ta' => '🌐 வெளியிட்ட வலைத்தளங்கள்', 'si' => '🌐 ප්‍රකාශිත අඩවි'],
        'p_tab_notifs' => ['en' => '🔔 Notifications', 'ta' => '🔔 அறிவிப்புகள்', 'si' => '🔔 දැනුම්දීම්'],
        'p_tab_reviews' => ['en' => '⭐ My Reviews', 'ta' => '⭐ எனது மதிப்புரைகள்', 'si' => '⭐ මගේ සමාලෝචන'],
        'p_sites_under' => ['en' => 'website(s) under your account', 'ta' => 'வலைத்தளங்கள் உங்கள் கணக்கில்', 'si' => 'ඔබේ ගිණුමේ ඇති අඩවි'],
        'p_btn_edit_profile' => ['en' => '✎ Edit Profile', 'ta' => '✎ சுயவிவரம்', 'si' => '✎ පැතිකඩ සංස්කරණය'],
        'p_empty_sites_t' => ['en' => 'No Published Websites Yet', 'ta' => 'இன்னும் வலைத்தளம் இல்லை', 'si' => 'තවම අඩවි නැහැ'],
        'p_empty_sites_s' => ['en' => 'Start building your first stunning AI-powered website now.', 'ta' => 'உங்கள் முதல் AI வலைத்தளத்தை இப்போது உருவாக்குங்கள்.', 'si' => 'ඔබේ පළමු AI වෙබ් අඩවිය දැන් සාදන්න.'],
        'p_btn_build_ai' => ['en' => 'Build a Website with AI →', 'ta' => 'AI உடன் உருவாக்கு →', 'si' => 'AI සමඟ සාදන්න →'],
        'p_no_notifs' => ['en' => "🔔 No notifications yet. You're all caught up!", 'ta' => '🔔 அறிவிப்புகள் இல்லை!', 'si' => '🔔 දැනුම්දීම් නැහැ!'],
        // ── Reviews tab ──
        'r_write' => ['en' => '⭐ Write a Review', 'ta' => '⭐ மதிப்புரை எழுதுக', 'si' => '⭐ සමාලෝචනයක් ලියන්න'],
        'r_posting_as' => ['en' => 'Posting as', 'ta' => 'பதிவிடுபவர்', 'si' => 'පළ කරන්නේ'],
        'r_approved_note' => ['en' => 'approved reviews appear on our homepage.', 'ta' => 'அங்கீகரித்தவை முகப்பில் தோன்றும்.', 'si' => 'අනුමත ඒවා මුල් පිටුවේ පළ වේ.'],
        'r_rating' => ['en' => 'Your Rating *', 'ta' => 'உங்கள் மதிப்பீடு *', 'si' => 'ඔබේ ශ්‍රේණිය *'],
        'r_rate_hint' => ['en' => 'Tap a star to rate (1–5).', 'ta' => 'மதிப்பிட நட்சத்திரத்தைத் தட்டுங்கள் (1–5).', 'si' => 'ශ්‍රේණියට තරුවක් ඔබන්න (1–5).'],
        'r_title' => ['en' => 'Review Title', 'ta' => 'தலைப்பு', 'si' => 'මාතෘකාව'],
        'r_optional' => ['en' => '(optional)', 'ta' => '(விருப்பம்)', 'si' => '(විකල්ප)'],
        'r_title_ph' => ['en' => 'e.g. Live in 2 minutes', 'ta' => 'எ.கா. 2 நிமிடத்தில் நேரலை', 'si' => 'උදා. විනාඩි 2න් සජීවීව'],
        'r_msg' => ['en' => 'Your Review *', 'ta' => 'உங்கள் மதிப்புரை *', 'si' => 'ඔබේ සමාලෝචනය *'],
        'r_msg_ph' => ['en' => 'What did you build? How was the experience? (min 10 characters)', 'ta' => 'நீங்கள் என்ன உருவாக்கினீர்கள்? (குறைந்தது 10 எழுத்துகள்)', 'si' => 'ඔබ කුමක් සෑදුවාද? (අවම අක්ෂර 10)'],
        'r_role' => ['en' => 'Your Role / Business Type', 'ta' => 'உங்கள் பணி / தொழில்', 'si' => 'ඔබේ භූමිකාව / ව්‍යාපාරය'],
        'r_role_ph' => ['en' => 'e.g. Restaurant Owner', 'ta' => 'எ.கா. உணவக உரிமையாளர்', 'si' => 'උදා. අවන්හල් හිමිකරු'],
        'r_submit' => ['en' => 'Submit Review', 'ta' => 'சமர்ப்பி', 'si' => 'යොමු කරන්න'],
        'r_yours' => ['en' => 'Your Reviews', 'ta' => 'உங்கள் மதிப்புரைகள்', 'si' => 'ඔබේ සමාලෝචන'],
        'r_empty' => ['en' => '⭐ No reviews yet. Share your experience above!', 'ta' => '⭐ இன்னும் இல்லை. மேலே பகிருங்கள்!', 'si' => '⭐ තවම නැහැ. ඉහතින් බෙදාගන්න!'],
        'r_edit' => ['en' => '✎ Edit', 'ta' => '✎ திருத்து', 'si' => '✎ සංස්කරණය'],
        'r_delete' => ['en' => '🗑 Delete', 'ta' => '🗑 நீக்கு', 'si' => '🗑 මකන්න'],
        'r_edit_title' => ['en' => '✎ Edit Review', 'ta' => '✎ மதிப்புரையைத் திருத்து', 'si' => '✎ සමාලෝචනය සංස්කරණය'],
        'r_save' => ['en' => 'Save Changes', 'ta' => 'சேமி', 'si' => 'සුරකින්න'],
        'r_del_title' => ['en' => '🗑 Delete Review?', 'ta' => '🗑 நீக்கவா?', 'si' => '🗑 මකා දමනවාද?'],
        'r_del_msg' => ['en' => 'This cannot be undone. For verification, please enter your account password.', 'ta' => 'இதை மீட்க முடியாது. உங்கள் கடவுச்சொல்லை உள்ளிடுங்கள்.', 'si' => 'මෙය ආපසු හැරවිය නොහැක. ඔබේ මුරපදය ඇතුළත් කරන්න.'],
        'r_pw' => ['en' => 'Account Password *', 'ta' => 'கணக்கு கடவுச்சொல் *', 'si' => 'ගිණුම් මුරපදය *'],
        'r_cancel' => ['en' => 'Cancel', 'ta' => 'ரத்து', 'si' => 'අවලංගු'],
        'r_del_go' => ['en' => 'Delete', 'ta' => 'நீக்கு', 'si' => 'මකන්න'],
        'r_st_pending' => ['en' => 'Pending', 'ta' => 'நிலுவை', 'si' => 'අපේක්ෂිත'],
        'r_st_approved' => ['en' => 'Approved', 'ta' => 'அங்கீகரிதம்', 'si' => 'අනුමත'],
        'r_st_rejected' => ['en' => 'Rejected', 'ta' => 'நிராகரிப்பு', 'si' => 'ප්‍රතික්ෂේප'],
        // ── Builder wizard ──
        'b_step1' => ['en' => 'Step 1 of 4 · Business Information', 'ta' => 'படி 1/4 · தொழில் விவரம்', 'si' => 'පියවර 1/4 · ව්‍යාපාර තොරතුරු'],
        'b_biz_name' => ['en' => 'Business / Brand Name', 'ta' => 'தொழில் / பிராண்ட் பெயர்', 'si' => 'ව්‍යාපාර / සන්නාම නාමය'],
        'b_biz_type' => ['en' => 'Industry / Category', 'ta' => 'துறை / வகை', 'si' => 'කර්මාන්තය / වර්ගය'],
        'b_biz_tagline' => ['en' => 'Core Mission / Tagline', 'ta' => 'நோக்கம் / வாசகம்', 'si' => 'මූලික අරමුණ / තේමා පාඨය'],
        'b_biz_audience' => ['en' => 'Target Audience', 'ta' => 'இலக்கு பார்வையாளர்', 'si' => 'ඉලක්ක ප්‍රේක්ෂකයින්'],
        'b_biz_services' => ['en' => 'Services / Products Offered', 'ta' => 'சேவைகள் / தயாரிப்புகள்', 'si' => 'සේවා / නිෂ්පාදන'],
        'b_biz_products' => ['en' => '🛒 Product List', 'ta' => '🛒 தயாரிப்பு பட்டியல்', 'si' => '🛒 නිෂ්පාදන ලැයිස්තුව'],
        'b_biz_reviews' => ['en' => '⭐ Client Reviews', 'ta' => '⭐ வாடிக்கையாளர் மதிப்புரைகள்', 'si' => '⭐ පාරිභෝගික සමාලෝචන'],
        'b_step2' => ['en' => 'Step 2 of 4 · Primary Design Direction', 'ta' => 'படி 2/4 · வடிவமைப்பு திசை', 'si' => 'පියවර 2/4 · ප්‍රධාන නිර්මාණ දිශාව'],
        'b_design_style' => ['en' => 'Design Aesthetic', 'ta' => 'வடிவமைப்பு அழகியல்', 'si' => 'නිර්මාණ ශෛලිය'],
        'b_style_modern' => ['en' => 'Modern & Clean', 'ta' => 'நவீன & சுத்தம்', 'si' => 'නවීන & පිරිසිදු'],
        'b_style_bold' => ['en' => 'Bold & Dynamic', 'ta' => 'துணிச்சல் & துடிப்பு', 'si' => 'නිර්භීත & ගතික'],
        'b_style_dark' => ['en' => 'Luxury & Dark', 'ta' => 'ஆடம்பர & இருள்', 'si' => 'සුඛෝපභෝගී & අඳුරු'],
        'b_design_dir' => ['en' => 'Describe your design direction (optional)', 'ta' => 'வடிவமைப்பு திசையை விவரியுங்கள்', 'si' => 'නිර්මාණ දිශාව විස්තර කරන්න'],
        'b_color' => ['en' => 'Brand Color Accent', 'ta' => 'பிராண்ட் நிறம்', 'si' => 'සන්නාම වර්ණය'],
        'b_taste' => ['en' => '✦ Design Intelligence', 'ta' => '✦ வடிவமைப்பு நுண்ணறிவு', 'si' => '✦ නිර්මාණ බුද්ධිය'],
        'b_vibe_auto' => ['en' => '✦ Auto', 'ta' => '✦ தானியங்கு', 'si' => '✦ ස්වයංක්‍රීය'],
        'b_vibe_min' => ['en' => 'Minimal', 'ta' => 'குறைந்தபட்ச', 'si' => 'අවම'],
        'b_vibe_prem' => ['en' => 'Premium', 'ta' => 'பிரீமியம்', 'si' => 'වාරික'],
        'b_vibe_play' => ['en' => 'Playful', 'ta' => 'விளையாட்டு', 'si' => 'සෙල්ලක්කාර'],
        'b_vibe_edit' => ['en' => 'Editorial', 'ta' => 'பத்திரிகை', 'si' => 'කතුවැකි'],
        'b_vibe_brut' => ['en' => 'Brutalist', 'ta' => 'முரட்டு', 'si' => 'රළු'],
        'b_vibe_trust' => ['en' => 'Trust-first', 'ta' => 'நம்பிக்கை', 'si' => 'විශ්වාසය'],
        'b_dial_var' => ['en' => 'Layout variance', 'ta' => 'அமைப்பு மாறுபாடு', 'si' => 'පිරිසැලසුම් විචලනය'],
        'b_dial_mot' => ['en' => 'Motion intensity', 'ta' => 'அசைவு தீவிரம்', 'si' => 'චලන තීව්‍රතාව'],
        'b_dial_den' => ['en' => 'Visual density', 'ta' => 'காட்சி அடர்த்தி', 'si' => 'දෘශ්‍ය ඝනත්වය'],
        'b_step3' => ['en' => 'Step 3 of 4 · Website Sections', 'ta' => 'படி 3/4 · வலைத்தள பிரிவுகள்', 'si' => 'පියවර 3/4 · අඩවි කොටස්'],
        'b_sections' => ['en' => 'Include Sections', 'ta' => 'பிரிவுகள்', 'si' => 'කොටස් ඇතුළත් කරන්න'],
        'b_sec_hero' => ['en' => 'Hero & Value Proposition', 'ta' => 'முகப்பு', 'si' => 'ප්‍රධාන කොටස'],
        'b_sec_services' => ['en' => 'Services & Offerings', 'ta' => 'சேவைகள்', 'si' => 'සේවාවන්'],
        'b_sec_about' => ['en' => 'About & Story', 'ta' => 'எங்களைப் பற்றி', 'si' => 'අප ගැන'],
        'b_sec_metrics' => ['en' => 'Key Metrics & Stats', 'ta' => 'புள்ளிவிவரங்கள்', 'si' => 'සංඛ්‍යාලේඛන'],
        'b_sec_shop' => ['en' => '🛒 Shop & Products (working cart)', 'ta' => '🛒 கடை & தயாரிப்புகள்', 'si' => '🛒 සාප්පුව & නිෂ්පාදන'],
        'b_sec_contact' => ['en' => 'Interactive Contact Section', 'ta' => 'தொடர்பு பிரிவு', 'si' => 'සම්බන්ධතා කොටස'],
        'b_sec_footer' => ['en' => 'Footer with Links', 'ta' => 'கீழ் பகுதி', 'si' => 'පාදකය'],
        'b_step4' => ['en' => 'Step 4 of 4 · Contact Info & Generate', 'ta' => 'படி 4/4 · தொடர்பு & உருவாக்கு', 'si' => 'පියවර 4/4 · සම්බන්ධතා & සාදන්න'],
        'b_phone' => ['en' => 'Phone Number', 'ta' => 'தொலைபேசி', 'si' => 'දුරකථන අංකය'],
        'b_email' => ['en' => 'Contact Email', 'ta' => 'மின்னஞ்சல்', 'si' => 'සම්බන්ධතා විද්‍යුත් තැපෑල'],
        'b_address' => ['en' => 'Headquarters / Location', 'ta' => 'முகவரி', 'si' => 'ලිපිනය'],
        'b_logo' => ['en' => '🏷️ Brand Logo URL', 'ta' => '🏷️ லோகோ URL', 'si' => '🏷️ සන්නාම ලාංඡන URL'],
        'b_social' => ['en' => '📣 Social Media Links', 'ta' => '📣 சமூக ஊடக இணைப்புகள்', 'si' => '📣 සමාජ මාධ්‍ය සබැඳි'],
        'b_gen_title' => ['en' => 'Ready to Generate', 'ta' => 'உருவாக்கத் தயார்', 'si' => 'සාදන්න සූදානම්'],
        'b_gen_btn' => ['en' => 'Generate 3 Style Variations', 'ta' => '3 வடிவங்களை உருவாக்கு', 'si' => 'මාදිලි 3ක් සාදන්න'],
        'b_back' => ['en' => '← Back', 'ta' => '← பின்', 'si' => '← ආපසු'],
        'b_next' => ['en' => 'Next →', 'ta' => 'அடுத்து →', 'si' => 'ඊළඟ →'],
        // ── AI brief launcher (added feature) ──
        'b_brief_t' => ['en' => '✨ AI Business Brief', 'ta' => '✨ AI தொழில் சுருக்கம்', 'si' => '✨ AI ව්‍යාපාර සාරාංශය'],
        // ── Industries (option values stay English for the AI backend) ──
        'b_ind0' => ['en' => 'Creative & Digital Agency', 'ta' => 'கிரியேட்டிவ் & டிஜிட்டல் ஏஜென்சி', 'si' => 'නිර්මාණාත්මක & ඩිජිටල් නියෝජිත'],
        'b_ind1' => ['en' => 'Restaurant, Cafe & Bar', 'ta' => 'உணவகம், கஃபே & பார்', 'si' => 'අවන්හල, කැෆේ & බාර්'],
        'b_ind2' => ['en' => 'Tech Startup & SaaS', 'ta' => 'டெக் ஸ்டார்ட்அப் & SaaS', 'si' => 'තාක්ෂණ ආරම්භක & SaaS'],
        'b_ind3' => ['en' => 'Professional Services & Legal', 'ta' => 'தொழில்முறை சேவை & சட்டம்', 'si' => 'වෘත්තීය සේවා & නීති'],
        'b_ind4' => ['en' => 'Healthcare & Wellness Clinic', 'ta' => 'சுகாதார & நல மையம்', 'si' => 'සෞඛ්‍ය & සුවතා සායනය'],
        'b_ind5' => ['en' => 'Real Estate & Architecture', 'ta' => 'ரியல் எஸ்டேட் & கட்டிடக்கலை', 'si' => 'දේපළ & ගෘහ නිර්මාණ'],
        'b_ind6' => ['en' => 'Fitness Center & Personal Trainer', 'ta' => 'உடற்பயிற்சி மையம்', 'si' => 'ව්‍යායාම මධ්‍යස්ථානය'],
        'b_ind7' => ['en' => 'E-Commerce & Retail', 'ta' => 'மின் வணிகம்', 'si' => 'අන්තර්ජාල වෙළඳාම'],
        'b_ind8' => ['en' => 'Consultant / Advisor', 'ta' => 'ஆலோசகர்', 'si' => 'උපදේශක'],
        'b_ind9' => ['en' => 'Coach (Life / Business)', 'ta' => 'பயிற்சியாளர்', 'si' => 'පුහුණුකරු'],
        'b_ind10' => ['en' => 'Therapist / Counselor', 'ta' => 'சிகிச்சையாளர்', 'si' => 'චිකිත්සක'],
        'b_ind11' => ['en' => 'Tradesperson (Plumber / Electrician / Handyman)', 'ta' => 'தொழில்நுட்ப பணியாளர்', 'si' => 'කාර්මික ශිල්පී'],
        'b_ind12' => ['en' => 'Cleaning Service', 'ta' => 'சுத்தம் சேவை', 'si' => 'පිරිසිදු කිරීමේ සේවය'],
        'b_ind13' => ['en' => 'Tutor / Trainer', 'ta' => 'ஆசிரியர் / பயிற்றுநர்', 'si' => 'ගුරු / පුහුණුකරු'],
        'b_ind14' => ['en' => 'Personal Portfolio & Creator', 'ta' => 'தனிப்பட்ட போர்ட்ஃபோலியோ', 'si' => 'පුද්ගලික කළඹ'],
        'b_ind15' => ['en' => 'School / College / Education', 'ta' => 'பள்ளி / கல்லூரி / கல்வி', 'si' => 'පාසල / විද්‍යාලය / අධ්‍යාපනය'],
        'b_ind16' => ['en' => 'Other', 'ta' => 'மற்றவை', 'si' => 'වෙනත්'],
        'b_brief_s' => ['en' => 'Type one natural sentence about your business. AI fills Steps 1–3 for you — you stay in control.', 'ta' => 'உங்கள் தொழில் பற்றி ஒரு வரி எழுதுங்கள். AI படிகளை நிரப்பும்.', 'si' => 'ඔබේ ව්‍යාපාරය ගැන එක් පේළියක් ලියන්න. AI පියවර පුරවයි.'],
        'b_brief_btn' => ['en' => '✨ Understand my business', 'ta' => '✨ என் தொழிலைப் புரிந்துகொள்', 'si' => '✨ මගේ ව්‍යාපාරය තේරුම් ගන්න'],
        'b_brandkit' => ['en' => '🎨 Brand Kit', 'ta' => '🎨 பிராண்ட் கிட்', 'si' => '🎨 සන්නාම කට්ටලය'],
    ];
}

function ui_t(string $key): string {
    static $dict = null;
    if ($dict === null) $dict = ui_dict();
    $lang = 'en';
    try {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $l = $_SESSION['customer_user']['language'] ?? ($_SESSION['ui_lang'] ?? 'en');
        if (ui_lang_valid($l)) $lang = $l;
    } catch (Throwable $e) {}
    if (isset($dict[$key][$lang])) return $dict[$key][$lang];
    if (isset($dict[$key]['en'])) return $dict[$key]['en'];
    return $key;
}
