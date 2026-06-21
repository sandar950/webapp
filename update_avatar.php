<?php
session_start();

// Login ဝင်ထားခြင်း မရှိပါက login.php သို့ ပြန်ပို့ရန်
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/core/db_connect.php';
require_once __DIR__ . '/lang/language.php';
require_once __DIR__ . '/core/image_helper.php';

$user_id = $_SESSION['user_id'];
$success_message = "";
$error_message = "";

// Avatar Upload လုပ်ဆောင်ခြင်း
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_POST['cropped_avatar_data'])) {
        $base64_string = $_POST['cropped_avatar_data'];
        
        // 1. Validate Base64 string and size
        if (!preg_match('/^data:image\/(jpeg|png|webp);base64,/', $base64_string, $matches)) {
            $error_message = 'Invalid image format. Only JPEG, PNG, and WEBP are allowed.';
        } else {
            list($type, $data) = explode(';', $base64_string);
            list(, $data)      = explode(',', $data);
            $decoded_data = base64_decode($data);
            
            // 2. Server-side file size check (e.g., max 5MB)
            if (strlen($decoded_data) > 5 * 1024 * 1024) {
                $error_message = 'File size is too large. Maximum is 5MB.';
            } else {
                $upload_dir = 'uploads/avatars/';
                if (!is_dir($upload_dir)) {
                    // Use more secure directory permissions
                    mkdir($upload_dir, 0755, true);
                }

                $ext = $matches[1]; // jpeg, png, or webp
                if ($ext === 'jpeg') $ext = 'jpg';
                
                // Create a temporary file to be processed by the image helper
                $temp_file = tempnam(sys_get_temp_dir(), 'avatar_');
                file_put_contents($temp_file, $decoded_data);
                
                $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
                $upload_path = $upload_dir . $new_filename;
                
                // 3. Use the secure image helper to compress and validate
                if (compressImage($temp_file, $upload_path, 80)) {
                    // ယခင်ပုံဟောင်းရှိပါက ဖျက်မည်
                    $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($row = $res->fetch_assoc()) {
                        if (!empty($row['avatar']) && file_exists($row['avatar']) && $row['avatar'] !== $upload_path) {
                            unlink($row['avatar']);
                        }
                    }
                    $stmt->close();
                    
                    $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $stmt->bind_param("si", $upload_path, $user_id);
                    $stmt->execute();
                    $stmt->close();
                    $success_message = __('avatar_update_success');
                } else {
                    $error_message = __('avatar_upload_error');
                }

                // Clean up the temporary file
                if (file_exists($temp_file)) {
                    unlink($temp_file);
                }
            }
        }
    } else {
        $error_message = __('avatar_crop_required');
    }
}

// လက်ရှိ User ၏ အချက်အလက်များကို Database မှ ဆွဲထုတ်ခြင်း
$stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<?php 
$page_title = __('update_avatar_page_title');
require_once __DIR__ . '/includes/header.php'; 
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<body class="w-full md:max-w-3xl lg:max-w-5xl mx-auto min-h-screen bg-gray-50 md:shadow-2xl md:border-x border-gray-200 transition-all duration-300 pb-20 md:pb-24 flex flex-col">
    
    <div class="bg-primary text-white flex items-center p-4 md:p-6 sticky top-0 z-20 shadow-md w-full">
        <a href="profile.php" class="mr-4 text-xl md:text-2xl w-6 md:w-10 hover:scale-110 transition-transform"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-xl md:text-2xl font-bold flex-1 text-center pr-6 md:pr-10 tracking-wide"><?= __('update_avatar_title') ?></h1>
    </div>

    <div class="p-4 md:p-8 flex-1 flex flex-col items-center justify-center md:mt-4 w-full">
        
        <div class="w-full max-w-md">
            <?php if (!empty($success_message)): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-5 py-4 rounded-xl relative mb-5 text-sm md:text-base font-bold shadow-sm flex items-center">
                    <i class="fas fa-check-circle text-green-500 text-xl mr-2"></i> <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-5 py-4 rounded-xl relative mb-5 text-sm md:text-base font-medium shadow-sm flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 text-xl mr-2"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" onsubmit="return checkSubmit(event)" class="bg-white p-6 md:p-10 rounded-2xl md:rounded-3xl shadow-lg border border-gray-100 text-center">
                
                <h2 class="text-gray-500 font-bold mb-6 hidden md:block text-lg border-b border-gray-100 pb-3"><?= __('change_profile_picture') ?? 'ပရိုဖိုင်ပုံ ပြောင်းလဲရန်' ?></h2>

                <div class="relative w-32 h-32 md:w-40 md:h-40 mx-auto mb-6 md:mb-8 group">
                    <?php if (!empty($user_data['avatar'])): ?>
                        <img src="<?= htmlspecialchars($user_data['avatar']) ?>" alt="Avatar" class="w-full h-full rounded-full object-cover shadow-md border-4 border-gray-100 group-hover:border-blue-200 transition-colors" id="avatarPreview">
                    <?php else: ?>
                        <div class="w-full h-full bg-blue-50 text-blue-300 rounded-full flex items-center justify-center shadow-inner border-4 border-gray-100 group-hover:border-blue-200 transition-colors" id="avatarPlaceholder"><i class="fas fa-user text-6xl md:text-7xl"></i></div>
                        <img src="" alt="Avatar" class="w-full h-full rounded-full object-cover shadow-md border-4 border-gray-100 hidden group-hover:border-blue-200 transition-colors" id="avatarPreview">
                    <?php endif; ?>
                </div>
                
                <div class="mb-6 md:mb-8">
                    <label for="avatarInput" class="cursor-pointer bg-blue-50 text-blue-700 hover:bg-blue-100 hover:shadow-sm px-5 md:px-6 py-2.5 md:py-3 rounded-xl text-sm md:text-base font-bold border border-blue-200 transition-all inline-flex items-center justify-center w-3/4">
                        <i class="fas fa-image mr-2 text-lg"></i> <?= __('select_new_image') ?>
                    </label>
                    <input type="file" id="avatarInput" accept="image/png, image/jpeg, image/jpg, image/webp" class="hidden" onchange="previewImage(event)">
                    <input type="hidden" id="cropped_avatar_data" name="cropped_avatar_data">
                </div>

                <button type="submit" class="w-full bg-primary hover:bg-blue-800 text-white font-bold py-3.5 md:py-4 rounded-xl text-lg md:text-xl shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
                    <i class="fas fa-upload mr-1.5"></i> <?= __('confirm_and_upload') ?>
                </button>
                
                <div class="mt-6 md:mt-8 text-center border-t border-gray-100 pt-5">
                    <a href="profile.php" class="text-gray-500 hover:text-primary text-sm md:text-base font-medium flex items-center justify-center transition-colors">
                        <i class="fas fa-arrow-left mr-1.5"></i> <?= __('back') ?>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div id="cropperModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[60] hidden flex items-center justify-center p-4">
        <div class="bg-white p-5 md:p-8 rounded-2xl md:rounded-3xl shadow-2xl w-full max-w-md md:max-w-lg animate-[slide-in_0.3s_ease-out]">
            <h3 class="text-lg md:text-xl font-bold text-gray-800 mb-4 flex items-center border-b border-gray-100 pb-3">
                <i class="fas fa-crop-alt mr-2 text-primary"></i> <?= __('crop_image_square') ?>
            </h3>
            
            <div class="w-full h-64 md:h-80 bg-gray-100 mb-5 rounded-xl overflow-hidden shadow-inner flex items-center justify-center border border-gray-200">
                <img id="imageToCrop" src="" class="max-w-full max-h-full block mx-auto">
            </div>
            
            <div class="flex justify-end gap-3 md:gap-4 mt-2">
                <button type="button" onclick="cancelCrop()" class="px-5 md:px-6 py-2.5 md:py-3 bg-gray-200 hover:bg-gray-300 rounded-xl text-gray-800 font-bold transition-colors text-sm md:text-base"><?= __('cancel_button') ?></button>
                <button type="button" onclick="applyCrop()" class="px-5 md:px-6 py-2.5 md:py-3 bg-primary hover:bg-blue-800 text-white rounded-xl font-bold shadow-md hover:shadow-lg transition-all hover:-translate-y-0.5 text-sm md:text-base"><i class="fas fa-check mr-1"></i> <?= __('crop_button') ?></button>
            </div>
        </div>
    </div>

    <script>
        let cropper = null;
        
        function previewImage(event) {
            const files = event.target.files;
            if (files && files.length > 0) {
                const file = files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imageToCrop = document.getElementById('imageToCrop');
                    imageToCrop.src = e.target.result;
                    document.getElementById('cropperModal').classList.remove('hidden');
                    if (cropper) cropper.destroy();
                    cropper = new Cropper(imageToCrop, {
                        aspectRatio: 1, // လေးထောင့် (Square) အချိုး
                        viewMode: 1,
                        autoCropArea: 1,
                    });
                };
                reader.readAsDataURL(file);
            }
        }
        
        function cancelCrop() {
            document.getElementById('cropperModal').classList.add('hidden');
            document.getElementById('avatarInput').value = '';
            if (cropper) cropper.destroy();
        }

        function applyCrop() {
            if (!cropper) return;
            const canvas = cropper.getCroppedCanvas({
                width: 400, // ပုံ Size ကို 400x400 ဖြင့်သာ ယူမည်
                height: 400
            });
            const base64Data = canvas.toDataURL('image/webp', 0.8);
            
            const preview = document.getElementById('avatarPreview');
            const placeholder = document.getElementById('avatarPlaceholder');
            preview.src = base64Data;
            preview.classList.remove('hidden');
            if (placeholder) placeholder.classList.add('hidden');
            
            // Base64 Data ကို Form ပို့ရန်အတွက် Hidden input သို့ ထည့်မည်
            document.getElementById('cropped_avatar_data').value = base64Data;
            
            document.getElementById('cropperModal').classList.add('hidden');
            cropper.destroy();
            cropper = null;
        }
        
        function checkSubmit(e) {
            if (!document.getElementById('cropped_avatar_data').value) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: '<?= __('warning') ?? "သတိပေးချက်" ?>',
                        text: '<?= __('avatar_crop_required_alert') ?>',
                        confirmButtonColor: '#1a428a',
                        customClass: { popup: 'rounded-2xl' }
                    });
                } else {
                    alert("<?= __('avatar_crop_required_alert') ?>");
                }
                e.preventDefault();
                return false;
            }
            return true;
        }
    </script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>