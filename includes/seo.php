<?php
// User ၏ လက်ရှိ ဘာသာစကားကို ဖမ်းယူမည်
$lang = $_SESSION['lang'] ?? 'mm'; 

// ၁။ Global Default SEO ကို အရင်ဆွဲထုတ်မည်
$default_seo = [
    'seo_title' => 'Thai 2D3D',
    'seo_description' => '',
    'seo_keywords' => '',
    'seo_image_url' => ''
];

if (isset($conn)) {
    // လက်ရှိ ဘာသာစကားနှင့် ကိုက်ညီသော Keys များကိုသာ ဆွဲထုတ်မည်
    $setting_keys = "'seo_title_{$lang}', 'seo_description_{$lang}', 'seo_keywords_{$lang}', 'seo_image_url'";
    $global_stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ({$setting_keys})");
    if ($global_stmt) {
        while ($row = $global_stmt->fetch_assoc()) {
            // $lang (ဥပမာ _mm, _en) ကို ဖြတ်ထုတ်ပြီး Normal key အဖြစ်သိမ်းမည်
            $normalized_key = str_replace("_{$lang}", "", $row['setting_key']);
            $default_seo[$normalized_key] = $row['setting_value'];
        }
    }
}

// ၂။ လက်ရှိရောက်နေသော PHP ဖိုင်နာမည်ကို ရှာမည် (ဥပမာ - index.php)
$current_page_name = basename($_SERVER['PHP_SELF']);

// ၃။ ထိုဖိုင်အတွက် သီးသန့် SEO သတ်မှတ်ထားခြင်း ရှိ/မရှိ ရှာမည်
$page_seo_data = [];
if (isset($conn)) {
    $page_stmt = $conn->prepare("SELECT seo_title_mm, seo_title_en, seo_description_mm, seo_description_en, seo_keywords_mm, seo_keywords_en, seo_image_url FROM page_seo WHERE page_name = ?");
    $page_stmt->bind_param("s", $current_page_name);
    $page_stmt->execute();
    $page_res = $page_stmt->get_result();
    if ($page_res->num_rows > 0) {
        $row = $page_res->fetch_assoc();
        $page_seo_data = [
            'seo_title' => $row["seo_title_{$lang}"],
            'seo_description' => $row["seo_description_{$lang}"],
            'seo_keywords' => $row["seo_keywords_{$lang}"],
            'seo_image_url' => $row['seo_image_url']
        ];
    }
    $page_stmt->close();
}

// ၄။ Final SEO အချက်အလက်များကို ဆုံးဖြတ်ခြင်း (Priority: 1. Page SEO, 2. Variable $page_title, 3. Global SEO)
$final_title = !empty($page_seo_data['seo_title']) ? $page_seo_data['seo_title'] : (isset($page_title) ? $page_title : $default_seo['seo_title']);
$final_description = !empty($page_seo_data['seo_description']) ? $page_seo_data['seo_description'] : $default_seo['seo_description'];
$final_keywords = !empty($page_seo_data['seo_keywords']) ? $page_seo_data['seo_keywords'] : $default_seo['seo_keywords'];
$final_image_path = !empty($page_seo_data['seo_image_url']) ? $page_seo_data['seo_image_url'] : $default_seo['seo_image_url'];

// Website ရဲ့ Base URL ကို ရှာခြင်း (og:image အတွက်)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'];
$base_url_seo = $protocol . $domainName . '/';

// Absolute Image URL ပြောင်းရန်
$final_image = '';
if (!empty($final_image_path)) {
    if (filter_var($final_image_path, FILTER_VALIDATE_URL)) {
        $final_image = $final_image_path; 
    } else {
        $final_image = $base_url_seo . ltrim($final_image_path, '/');
    }
}
?>

<title><?= htmlspecialchars($final_title) ?></title>
<meta name="description" content="<?= htmlspecialchars($final_description) ?>">
<meta name="keywords" content="<?= htmlspecialchars($final_keywords) ?>">
<meta name="author" content="<?= htmlspecialchars($default_seo['seo_title'] ?? 'Thai 2D3D') ?>">

<meta property="og:type" content="website">
<meta property="og:url" content="<?= $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>">
<meta property="og:title" content="<?= htmlspecialchars($final_title) ?>">
<meta property="og:description" content="<?= htmlspecialchars($final_description) ?>">
<?php if($final_image): ?>
<meta property="og:image" content="<?= htmlspecialchars($final_image) ?>">
<?php endif; ?>

<meta property="twitter:card" content="summary_large_image">
<meta property="twitter:url" content="<?= $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>">
<meta property="twitter:title" content="<?= htmlspecialchars($final_title) ?>">
<meta property="twitter:description" content="<?= htmlspecialchars($final_description) ?>">
<?php if($final_image): ?>
<meta property="twitter:image" content="<?= htmlspecialchars($final_image) ?>">
<?php endif; ?>
