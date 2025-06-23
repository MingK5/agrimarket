<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$user = $_SESSION['user'];
$id   = $user['id'];

// map userType_Id to a simple role name
$roleMap = [
    1 => 'admin',
    2 => 'staff',
    3 => 'vendor',
    4 => 'customer'
];
$role = $roleMap[$user['userType_Id']] ?? '';

$errorMessage   = "";
$successMessage = "";

// initial fetch of users row
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$userData = $stmt->fetch();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- 1) common user fields ---
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $address  = trim($_POST['address']);

    // password change?
    $newPassword     = $_POST['new_password']    ?? '';
    $confirmPassword = $_POST['confirm_password']?? '';
    $passwordPattern = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@\$!%*#?&])[A-Za-z\d@\\$!%*#?&]{8,}$/";

    if ($newPassword !== '') {
        if ($newPassword !== $confirmPassword) {
            $errorMessage = "Passwords do not match.";
        } elseif (!preg_match($passwordPattern, $newPassword)) {
            $errorMessage = "Password must include uppercase, lowercase, digit, special character and be at least 8 characters.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET password = SHA2(?,256) WHERE id = ?");
            $stmt->execute([$newPassword, $id]);
        }
    }

    if (!$errorMessage) {
        // update common columns
        $stmt = $pdo->prepare("
            UPDATE users
               SET username = ?, email = ?, phone = ?, address = ?
             WHERE id = ?
        ");
        $stmt->execute([$username, $email, $phone, $address, $id]);

        // --- 2) vendor-specific ---
        if ($role === 'vendor') {
            // ... your existing vendor logic untouched ...
            $businessRegNo   = trim($_POST['business_reg_no']);
            $bankAccount     = trim($_POST['bank_account']);
            $listArr = array_filter(array_map('trim',
              explode(',', $_POST['service_listings'] ?? '')
            ));
            $serviceListings = implode(',', $listArr);

            if ($businessRegNo === '') {
                $errorMessage = "Business Registration Number is required.";
            }
            if ($serviceListings === '') {
                $errorMessage = "Please select at least one service listing.";
            }
            if (!ctype_digit($bankAccount)) {
                $errorMessage = "Bank Account must be numbers only.";
            }

            if (!$errorMessage) {
                $stmt = $pdo->prepare("
                    UPDATE users
                       SET business_reg_no  = ?,
                           bank_account     = ?,
                           service_listings = ?
                     WHERE id = ?
                ");
                $stmt->execute([
                    $businessRegNo,
                    $bankAccount,
                    $serviceListings,
                    $id
                ]);
                $successMessage = "Profile updated successfully.";
            }
        }
        if (!$errorMessage && $successMessage === "") {
            $successMessage = "Profile updated successfully.";
        }

        // re-fetch so form shows updated data
        if (!$errorMessage) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $userData = $stmt->fetch();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Profile</title>
    <style>
      form { display: flex; flex-direction: column; gap: 10px; }
      .form-group { margin-bottom: 15px; position: relative; }
      label { font-weight: bold; display: block; margin-bottom: 5px; }
      input, select, textarea { width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px; box-sizing:border-box; }
      button { background:#2e7d32; color:#fff; padding:10px 15px; border:none; border-radius:6px; cursor:pointer; font-weight:bold; }
      button:hover { background:#256428; }
      .error-message { color:red; font-weight:bold; margin-bottom:10px; }
      .success-message { color:green; font-weight:bold; margin-bottom:10px; }
      .password-wrapper { position:relative; }
      .toggle-icon { position:absolute; top:33px; right:12px; width:22px; height:22px; cursor:pointer; }
      .tag { display: inline-flex; align-items: center; background-color: #2e7d32; color: #fff; padding: 4px 10px; margin: 2px; border-radius: 12px; font-size: 13px; line-height: 1; }
      .tag span { margin-left: 8px; cursor: pointer; font-weight: bold; }
      .tag span:hover { color: #ddd; }
      .hint { color: #555; font-style: italic; font-size: 13px; margin-top: 4px; }
      hr { border: none; border-top: 1px solid #ccc; margin: 20px 0; }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div style="max-width:600px; margin:40px auto;">
  <h2>Edit Profile</h2>

  <?php if ($errorMessage): ?>
    <div class="error-message"><?= htmlspecialchars($errorMessage) ?></div>
  <?php elseif ($successMessage): ?>
    <div class="success-message"><?= htmlspecialchars($successMessage) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <!-- common fields -->
    <div class="form-group">
      <label>User ID</label>
      <input type="text" value="<?= htmlspecialchars($userData['id']) ?>" disabled>
    </div>
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" value="<?= htmlspecialchars($userData['username']) ?>" required>
    </div>
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" value="<?= htmlspecialchars($userData['email']) ?>" required>
    </div>
    <div class="form-group">
      <label>Phone (Malaysia only)</label>
      <input
        type="text"
        name="phone"
        pattern="01\d{8,9}"
        value="<?= htmlspecialchars($userData['phone']) ?>"
        required
      >
      <p class="hint">
        Only numbers. Must start with <code>01</code> (e.g. <code>0123456789</code>), not country code <code>60</code>.
      </p>
    </div>
    <div class="form-group">
      <label>Address</label>
      <input type="text" name="address" value="<?= htmlspecialchars($userData['address']) ?>" required>
    </div>

    <!-- vendor section (unchanged) -->
    <?php if ($role === 'vendor'): ?>
      <?php
        $serviceArray = array_filter(array_map('trim',
          explode(',', $userData['service_listings'] ?? '')
        ));
      ?>
      <div class="form-group">
        <label>Business Registration Number</label>
        <input type="text" name="business_reg_no" value="<?= htmlspecialchars($userData['business_reg_no']) ?>" required>
      </div>
      <div class="form-group">
        <label>Bank Account No</label>
        <input type="text" name="bank_account" pattern="\d+" maxlength="30"
               value="<?= htmlspecialchars($userData['bank_account']) ?>" required>
        <p class="hint">Only numbers are allowed.</p>
      </div>
      <div class="form-group">
        <label>Service Listings (select at least 1)</label>
        <select id="service_select" onchange="addService()">
          <option value="">-- Select a service --</option>
          <option value="Fish Farming">Fish Farming</option>
          <option value="Miscellaneous Products">Miscellaneous Products</option>
          <option value="Dairy">Dairy</option>
          <option value="Edible Forestry Products">Edible Forestry Products</option>
          <option value="Crops">Crops</option>
          <option value="Livestock">Livestock</option>
        </select>
        <div id="tag_container">
          <?php foreach ($serviceArray as $svc): ?>
            <span class="tag">
              <?= htmlspecialchars($svc) ?>
              <span onclick="removeService(this,'<?= htmlspecialchars($svc) ?>')">×</span>
            </span>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="service_listings" id="service_listings"
               value="<?= htmlspecialchars(implode(',', $serviceArray)) ?>">
        <p class="hint">You must select at least 1.</p>
      </div>
      <div class="form-group">
        <label>Subscription Tier</label>
        <input type="text" readonly
               value="<?= ucfirst($userData['subscription_tier']) ?>"
               style="background:#f9f9f9;">
        <?php if ($userData['subscription_tier'] === 'free'): ?>
          <p><a href="upgrade_subscription.php" style="color:green;font-weight:bold;">
            Upgrade to Premium
          </a></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <hr>
    <h4>Change Password</h4>
    <div class="form-group password-wrapper">
      <label for="new_password">New Password</label>
      <input type="password" name="new_password" id="new_password">
      <img src="../assets/close_eye.png" class="toggle-icon"
           onclick="togglePassword(this,'new_password')">
    </div>
    <div class="form-group password-wrapper">
      <label for="confirm_password">Confirm Password</label>
      <input type="password" name="confirm_password" id="confirm_password">
      <img src="../assets/close_eye.png" class="toggle-icon"
           onclick="togglePassword(this,'confirm_password')">
    </div>

    <button type="submit">Save Changes</button>
  </form>
</div>

<script>
function togglePassword(img, inputId) {
  const fld = document.getElementById(inputId);
  fld.type = fld.type === 'password' ? 'text' : 'password';
  img.src  = fld.type === 'text'
            ? '../assets/open_eye.png'
            : '../assets/close_eye.png';
}

// Service-listings tag logic (unchanged)
const serviceSelect   = document.getElementById('service_select');
const originalOptions = Array.from(serviceSelect.options)
  .filter(o => o.value)
  .map(o => ({ value:o.value, text:o.textContent }));

function addService() {
  const val = serviceSelect.value;
  if (!val) return;
  const span = document.createElement('span');
  span.className = 'tag';
  span.textContent = val;
  const rm = document.createElement('span');
  rm.textContent = '×';
  rm.onclick = () => removeService(rm,val);
  span.appendChild(rm);
  document.getElementById('tag_container').appendChild(span);
  serviceSelect.querySelector(`option[value="${val}"]`).remove();
  updateServiceInput();
  serviceSelect.value = '';
}

function removeService(el,val) {
  el.parentNode.remove();
  const info = originalOptions.find(o=>o.value===val);
  if (info) {
    const opt = document.createElement('option');
    opt.value = info.value;
    opt.textContent = info.text;
    serviceSelect.appendChild(opt);
  }
  updateServiceInput();
}

function updateServiceInput() {
  const tags = document.querySelectorAll('#tag_container .tag');
  const vals = Array.from(tags).map(t=>t.firstChild.textContent);
  document.getElementById('service_listings').value = vals.join(',');
}

// remove already-selected on load
document.addEventListener('DOMContentLoaded', ()=>{
  const existing = <?= json_encode($serviceArray ?? []) ?>;
  existing.forEach(val=>{
    const opt = serviceSelect.querySelector(`option[value="${val}"]`);
    opt && opt.remove();
  });
});
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>
