<?php
// ============================================
// ShareHub - File/Image/Video Sharing App
// ============================================

session_start();

// تنظیمات
$baseDir     = __DIR__;
$uploadDir   = $baseDir . '/uploads';
$dataFile    = $baseDir . '/data.json';
$baseUrl     = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/');

// ساخت پوشه‌ها
foreach (['images', 'videos', 'files'] as $dir) {
    if (!is_dir("$uploadDir/$dir")) mkdir("$uploadDir/$dir", 0755, true);
}
if (!file_exists($dataFile)) file_put_contents($dataFile, json_encode([]));

// بارگذاری داده‌ها
function getData($file) {
    return json_decode(file_get_contents($file), true) ?: [];
}
function saveData($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ============================================
// آپلود فایل
// ============================================
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'خطا در آپلود فایل';
        $messageType = 'error';
    } else {
        $maxSize = 100 * 1024 * 1024; // 100MB
        if ($file['size'] > $maxSize) {
            $message = 'حجم فایل بیشتر از ۱۰۰ مگابایت است';
            $messageType = 'error';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $imageExt = ['jpg','jpeg','png','gif','webp','svg','bmp'];
            $videoExt = ['mp4','webm','ogg','mov','avi','mkv'];
            
            if (in_array($ext, $imageExt)) $type = 'images';
            elseif (in_array($ext, $videoExt)) $type = 'videos';
            else $type = 'files';
            
            $newName = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
            $dest = "$uploadDir/$type/$newName";
            
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $data = getData($dataFile);
                $data[] = [
                    'id'        => uniqid(),
                    'name'      => $file['name'],
                    'stored'    => $newName,
                    'type'      => $type,
                    'size'      => $file['size'],
                    'ext'       => $ext,
                    'date'      => date('Y-m-d H:i:s'),
                ];
                saveData($dataFile, $data);
                $message = '✅ فایل با موفقیت آپلود شد';
                $messageType = 'success';
            } else {
                $message = 'خطا در ذخیره فایل';
                $messageType = 'error';
            }
        }
    }
}

// ============================================
// حذف فایل
// ============================================
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $data = getData($dataFile);
    foreach ($data as $k => $item) {
        if ($item['id'] === $id) {
            @unlink("$uploadDir/{$item['type']}/{$item['stored']}");
            unset($data[$k]);
            break;
        }
    }
    saveData($dataFile, array_values($data));
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// بارگذاری لیست
$files = getData($dataFile);
$files = array_reverse($files);
$filter = $_GET['filter'] ?? 'all';
if ($filter !== 'all') {
    $files = array_filter($files, fn($f) => $f['type'] === $filter);
}

// آمار
$allFiles = getData($dataFile);
$stats = [
    'images' => count(array_filter($allFiles, fn($f) => $f['type'] === 'images')),
    'videos' => count(array_filter($allFiles, fn($f) => $f['type'] === 'videos')),
    'files'  => count(array_filter($allFiles, fn($f) => $f['type'] === 'files')),
];

function formatSize($bytes) {
    if ($bytes >= 1048576) return round($bytes/1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes/1024, 2) . ' KB';
    return $bytes . ' B';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ShareHub — اشتراک‌گذاری فایل</title>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --bg: #0a0e1a;
    --bg-2: #131828;
    --card: rgba(255,255,255,0.04);
    --border: rgba(255,255,255,0.08);
    --primary: #6366f1;
    --primary-2: #8b5cf6;
    --accent: #06b6d4;
    --text: #e2e8f0;
    --text-dim: #94a3b8;
    --success: #10b981;
    --error: #ef4444;
}
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: 'Vazirmatn', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    overflow-x: hidden;
}
body::before {
    content:'';
    position: fixed;
    inset: 0;
    background:
        radial-gradient(circle at 10% 20%, rgba(99,102,241,0.15), transparent 40%),
        radial-gradient(circle at 90% 80%, rgba(139,92,246,0.12), transparent 40%),
        radial-gradient(circle at 50% 50%, rgba(6,182,212,0.08), transparent 50%);
    z-index: -1;
    pointer-events: none;
}

/* ============ هدر ============ */
.header {
    padding: 30px 20px;
    text-align: center;
    position: relative;
}
.logo {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    font-size: 32px;
    font-weight: 800;
    background: linear-gradient(135deg, #6366f1, #8b5cf6, #06b6d4);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    letter-spacing: -0.5px;
}
.logo-icon {
    width: 48px; height: 48px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 14px;
    display: grid;
    place-items: center;
    font-size: 24px;
    box-shadow: 0 8px 24px rgba(99,102,241,0.4);
}
.subtitle {
    margin-top: 12px;
    color: var(--text-dim);
    font-size: 15px;
}

/* ============ کانتینر ============ */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px 60px;
}

/* ============ آمار ============ */
.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
    margin-bottom: 30px;
}
.stat-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 20px;
    text-align: center;
    backdrop-filter: blur(10px);
    transition: 0.3s;
}
.stat-card:hover { transform: translateY(-4px); border-color: var(--primary); }
.stat-num {
    font-size: 28px;
    font-weight: 800;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}
.stat-label { color: var(--text-dim); font-size: 13px; margin-top: 4px; }

/* ============ آپلود ============ */
.upload-box {
    background: var(--card);
    border: 2px dashed var(--border);
    border-radius: 24px;
    padding: 50px 30px;
    text-align: center;
    margin-bottom: 30px;
    backdrop-filter: blur(10px);
    transition: 0.3s;
    cursor: pointer;
    position: relative;
}
.upload-box:hover, .upload-box.dragover {
    border-color: var(--primary);
    background: rgba(99,102,241,0.08);
    transform: translateY(-2px);
}
.upload-icon {
    font-size: 56px;
    margin-bottom: 16px;
    display: inline-block;
    animation: float 3s ease-in-out infinite;
}
@keyframes float {
    0%,100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}
.upload-box h3 { font-size: 20px; margin-bottom: 8px; font-weight: 600; }
.upload-box p { color: var(--text-dim); font-size: 14px; margin-bottom: 20px; }
.upload-btn {
    display: inline-block;
    padding: 12px 32px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    border: none;
    border-radius: 12px;
    font-family: inherit;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 8px 24px rgba(99,102,241,0.35);
    transition: 0.3s;
}
.upload-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(99,102,241,0.5); }
#fileInput { display: none; }

/* ============ پیام ============ */
.message {
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-weight: 500;
    animation: slideIn 0.3s;
}
@keyframes slideIn { from { opacity:0; transform: translateY(-10px); } to { opacity:1; transform: translateY(0); } }
.message.success { background: rgba(16,185,129,0.15); color: var(--success); border: 1px solid rgba(16,185,129,0.3); }
.message.error { background: rgba(239,68,68,0.15); color: var(--error); border: 1px solid rgba(239,68,68,0.3); }

/* ============ تب‌ها ============ */
.tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.tab {
    padding: 10px 20px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text-dim);
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: 0.3s;
}
.tab:hover { border-color: var(--primary); color: var(--text); }
.tab.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 4px 16px rgba(99,102,241,0.4);
}

/* ============ گرید فایل‌ها ============ */
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 20px;
}
.file-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    overflow: hidden;
    backdrop-filter: blur(10px);
    transition: 0.3s;
    display: flex;
    flex-direction: column;
}
.file-card:hover {
    transform: translateY(-6px);
    border-color: var(--primary);
    box-shadow: 0 20px 40px rgba(0,0,0,0.4);
}
.preview {
    height: 180px;
    background: var(--bg-2);
    display: grid;
    place-items: center;
    overflow: hidden;
    position: relative;
}
.preview img, .preview video {
    width: 100%; height: 100%;
    object-fit: cover;
}
.preview .icon {
    font-size: 56px;
    opacity: 0.7;
}
.file-info { padding: 16px; flex: 1; display: flex; flex-direction: column; }
.file-name {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 6px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.file-meta {
    font-size: 12px;
    color: var(--text-dim);
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
}
.file-actions { display: flex; gap: 6px; margin-top: auto; }
.btn {
    flex: 1;
    padding: 9px 10px;
    border: none;
    border-radius: 10px;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    transition: 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}
.btn-copy { background: rgba(99,102,241,0.15); color: #a5b4fc; }
.btn-copy:hover { background: rgba(99,102,241,0.3); }
.btn-view { background: rgba(6,182,212,0.15); color: #67e8f9; }
.btn-view:hover { background: rgba(6,182,212,0.3); }
.btn-del { background: rgba(239,68,68,0.15); color: #fca5a5; }
.btn-del:hover { background: rgba(239,68,68,0.3); }

/* ============ خالی ============ */
.empty {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-dim);
}
.empty-icon { font-size: 64px; margin-bottom: 16px; opacity: 0.5; }

/* ============ توست ============ */
.toast {
    position: fixed;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%) translateY(100px);
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
    padding: 14px 28px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
    box-shadow: 0 12px 32px rgba(16,185,129,0.5);
    opacity: 0;
    transition: 0.4s;
    z-index: 1000;
}
.toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }

/* ============ مودال ============ */
.modal {
    position: fixed; inset:0;
    background: rgba(0,0,0,0.85);
    display: none;
    place-items: center;
    z-index: 999;
    padding: 20px;
    backdrop-filter: blur(6px);
}
.modal.show { display: grid; }
.modal img, .modal video { max-width: 100%; max-height: 85vh; border-radius: 16px; }
.modal-close {
    position: absolute; top: 20px; right: 20px;
    width: 44px; height: 44px;
    background: rgba(255,255,255,0.1);
    border: none;
    border-radius: 50%;
    color: #fff;
    font-size: 22px;
    cursor: pointer;
}
.modal-close:hover { background: rgba(255,255,255,0.2); }

@media (max-width: 640px) {
    .logo { font-size: 24px; }
    .logo-icon { width: 40px; height: 40px; font-size: 20px; }
    .upload-box { padding: 30px 20px; }
    .grid { grid-template-columns: 1fr 1fr; gap: 12px; }
    .preview { height: 130px; }
    .file-info { padding: 10px; }
    .file-name { font-size: 12px; }
    .btn { font-size: 10px; padding: 7px 4px; }
}
</style>
</head>
<body>

<div class="header">
    <div class="logo">
        <div class="logo-icon">📤</div>
        ShareHub
    </div>
    <p class="subtitle">آپلود، اشتراک‌گذاری و دریافت لینک مستقیم</p>
</div>

<div class="container">

    <?php if ($message): ?>
        <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- آمار -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-num"><?= $stats['images'] ?></div>
            <div class="stat-label">🖼 عکس‌ها</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?= $stats['videos'] ?></div>
            <div class="stat-label">🎬 ویدیوها</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?= $stats['files'] ?></div>
            <div class="stat-label">📄 فایل‌ها</div>
        </div>
    </div>

    <!-- آپلود -->
    <form method="POST" enctype="multipart/form-data" id="uploadForm">
        <div class="upload-box" id="dropZone">
            <div class="upload-icon">📁</div>
            <h3>فایل را اینجا رها کنید</h3>
            <p>عکس، ویدیو یا هر فایل دیگر — حداکثر ۱۰۰ مگابایت</p>
            <button type="button" class="upload-btn" onclick="document.getElementById('fileInput').click()">
                انتخاب فایل
            </button>
            <input type="file" name="file" id="fileInput" required>
        </div>
    </form>

    <!-- تب‌ها -->
    <div class="tabs">
        <a href="?filter=all" class="tab <?= $filter==='all'?'active':'' ?>">🌐 همه</a>
        <a href="?filter=images" class="tab <?= $filter==='images'?'active':'' ?>">🖼 عکس‌ها</a>
        <a href="?filter=videos" class="tab <?= $filter==='videos'?'active':'' ?>">🎬 ویدیوها</a>
        <a href="?filter=files" class="tab <?= $filter==='files'?'active':'' ?>">📄 فایل‌ها</a>
    </div>

    <!-- گرید -->
    <?php if (empty($files)): ?>
        <div class="empty">
            <div class="empty-icon">📭</div>
            <p>هنوز هیچ فایلی آپلود نشده است</p>
        </div>
    <?php else: ?>
        <div class="grid">
        <?php foreach ($files as $f): 
            $fileUrl = "$baseUrl/uploads/{$f['type']}/{$f['stored']}";
        ?>
            <div class="file-card">
                <div class="preview">
                    <?php if ($f['type'] === 'images'): ?>
                        <img src="<?= $fileUrl ?>" alt="" loading="lazy" onclick="openModal('<?= $fileUrl ?>','image')">
                    <?php elseif ($f['type'] === 'videos'): ?>
                        <video src="<?= $fileUrl ?>" preload="metadata" onclick="openModal('<?= $fileUrl ?>','video')"></video>
                    <?php else: ?>
                        <div class="icon">📄</div>
                    <?php endif; ?>
                </div>
                <div class="file-info">
                    <div class="file-name" title="<?= htmlspecialchars($f['name']) ?>">
                        <?= htmlspecialchars($f['name']) ?>
                    </div>
                    <div class="file-meta">
                        <span><?= formatSize($f['size']) ?></span>
                        <span><?= date('Y/m/d', strtotime($f['date'])) ?></span>
                    </div>
                    <div class="file-actions">
                        <button class="btn btn-copy" onclick="copyLink('<?= $fileUrl ?>')">🔗 کپی</button>
                        <a class="btn btn-view" href="<?= $fileUrl ?>" target="_blank">👁 باز</a>
                        <a class="btn btn-del" href="?delete=<?= $f['id'] ?>" onclick="return confirm('حذف شود؟')">🗑</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<!-- توست -->
<div class="toast" id="toast">لینک کپی شد!</div>

<!-- مودال -->
<div class="modal" id="modal" onclick="if(event.target.id==='modal')closeModal()">
    <button class="modal-close" onclick="closeModal()">✕</button>
    <div id="modalContent"></div>
</div>

<script>
// آپلود خودکار با انتخاب فایل
const fileInput = document.getElementById('fileInput');
const uploadForm = document.getElementById('uploadForm');
const dropZone = document.getElementById('dropZone');

fileInput.addEventListener('change', () => {
    if (fileInput.files.length) uploadForm.submit();
});

// Drag & Drop
['dragenter','dragover'].forEach(e => dropZone.addEventListener(e, ev => {
    ev.preventDefault();
    dropZone.classList.add('dragover');
}));
['dragleave','drop'].forEach(e => dropZone.addEventListener(e, ev => {
    ev.preventDefault();
    dropZone.classList.remove('dragover');
}));
dropZone.addEventListener('drop', ev => {
    if (ev.dataTransfer.files.length) {
        fileInput.files = ev.dataTransfer.files;
        uploadForm.submit();
    }
});

// کپی لینک
function copyLink(url) {
    navigator.clipboard.writeText(url).then(() => showToast('🔗 لینک کپی شد!'));
}
function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2200);
}

// مودال
function openModal(url, type) {
    const m = document.getElementById('modal');
    const c = document.getElementById('modalContent');
    c.innerHTML = type === 'image'
        ? `<img src="${url}">`
        : `<video src="${url}" controls autoplay></video>`;
    m.classList.add('show');
}
function closeModal() {
    const m = document.getElementById('modal');
    m.classList.remove('show');
    document.getElementById('modalContent').innerHTML = '';
}
</script>

</body>
  </html>
