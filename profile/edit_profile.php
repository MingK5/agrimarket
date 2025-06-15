<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$user = $_SESSION['user'];
$id   = $user['id'];
$role = $user['role'];

// initial fetch of users row
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$userData = $stmt->fetch();

// fetch role-specific info
if ($role === 'admin') {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE user_id = ?");
    $stmt->execute([$id]);
    $adminData = $stmt->fetch();
}
elseif ($role === 'staff') {
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE user_id = ?");
    $stmt->execute([$id]);
    $staffData = $stmt->fetch();
}

$errorMessage   = "";
$successMessage = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Common user fields
    $fullName      = trim($_POST['full_name']);
    $username      = trim($_POST['username']);
    $email         = trim($_POST['email']);
    $phone         = trim($_POST['phone']);
    $street        = trim($_POST['street']);
    $postcode      = trim($_POST['postcode']);
    $city          = trim($_POST['city']);
    $state         = trim($_POST['state']);

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
            $stmt = $pdo->prepare("UPDATE users SET password = SHA2(?, 256) WHERE id = ?");
            $stmt->execute([$newPassword, $id]);
        }
    }

    if (!$errorMessage) {
        // update users table
        $stmt = $pdo->prepare("
            UPDATE users
               SET full_name = ?, username = ?, email = ?, phone = ?,
                   street    = ?, postcode = ?, city  = ?, state = ?
             WHERE id = ?
        ");
        $stmt->execute([
            $fullName, $username, $email, $phone,
            $street,   $postcode, $city,  $state,
            $id
        ]);

        // 2) Vendor‐specific
        if ($role === 'vendor') {
            // fetch existing
            $stmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
            $stmt->execute([$id]);
            $vendor = $stmt->fetch();

            $businessName    = trim($_POST['business_name']);
            $businessRegNo   = trim($_POST['business_reg_no']);
            $bankAccount     = trim($_POST['bank_account']);
            $websiteURL      = trim($_POST['website_url']);
            $listArr = array_filter(array_map('trim',
            explode(',', $_POST['service_listings'] ?? '')
            ));
            $serviceListings = implode(',', $listArr);
            $vendorUploads   = [];

            // ensure Business Name is filled
            if ($businessName === '') {
                $errorMessage = "Business Name is required.";
            }

            // ensure at least one service listing
            if ($serviceListings === '') {
                $errorMessage = "Please select at least one service listing.";
            }

            // handle uploads
            $allowedExts = ['pdf','jpg','jpeg','png'];
            $maxSize     = 2 * 1024 * 1024;
            foreach (['business_doc','bank_doc'] as $field) {
                if (
                    isset($_FILES[$field]) &&
                    $_FILES[$field]['error'] === UPLOAD_ERR_OK
                ) {
                    $ext  = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                    $size = $_FILES[$field]['size'];

                    if (!in_array($ext, $allowedExts)) {
                        $errorMessage = "Only PDF, JPG, JPEG, PNG allowed for {$field}.";
                        break;
                    }
                    if ($size > $maxSize) {
                        $errorMessage = "File for {$field} exceeds 2MB.";
                        break;
                    }

                    // delete old
                    foreach (glob(__DIR__ . "/../uploads/{$field}_user_{$id}.*") as $old) {
                        @unlink($old);
                    }

                    $filename    = "{$field}_user_{$id}.{$ext}";
                    $destination = __DIR__ . "/../uploads/{$filename}";
                    if (move_uploaded_file($_FILES[$field]['tmp_name'], $destination)) {
                        $vendorUploads[$field] = $filename;
                    }
                } elseif (empty($vendor[$field])) {
                    $errorMessage = ucfirst(str_replace('_',' ',$field)) . " is required.";
                    break;
                }
            }

            if (!$errorMessage) {
                // build UPDATE
                $sql    = "
                    UPDATE vendors
                       SET business_name    = ?,
                           business_reg_no = ?,
                           bank_account     = ?,
                           website_url      = ?,
                           service_listings = ?
                ";
                $params = [
                    $businessName,
                    $businessRegNo,
                    $bankAccount,
                    $websiteURL,
                    $serviceListings
                ];

                if (isset($vendorUploads['business_doc'])) {
                    $sql       .= ", business_doc = ?";
                    $params[]  = $vendorUploads['business_doc'];
                }
                if (isset($vendorUploads['bank_doc'])) {
                    $sql       .= ", bank_doc = ?";
                    $params[]  = $vendorUploads['bank_doc'];
                }

                $sql      .= " WHERE user_id = ?";
                $params[]  = $id;

                $pdo->prepare($sql)->execute($params);
                $successMessage = "Profile updated successfully.";
            }
        }
        // 3) Customer‐specific
        elseif ($role === 'customer') {
            $preferred = trim($_POST['preferred_payment'] ?? '');
            $pdo->prepare("
                UPDATE customers
                   SET preferred_payment = ?
                 WHERE user_id = ?
            ")->execute([$preferred, $id]);
            $successMessage = "Profile updated successfully.";
        }

        if (!$errorMessage && $successMessage === "") {
            $successMessage = "Profile updated successfully.";
        }

        // 4) re‐fetch users row so updated full_name shows immediately
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
      input, select { width:100%; padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:14px; box-sizing:border-box; }
      button { background:#2e7d32; color:#fff; padding:10px 15px; border:none; border-radius:6px; cursor:pointer; font-weight:bold; }
      button:hover { background:#256428; }
      .error-message { color:red; font-weight:bold; margin-bottom:10px; }
      .success-message { color:green; font-weight:bold; margin-bottom:10px; }
      .password-wrapper { position:relative; }
      .toggle-icon { position:absolute; top:33px; right:12px; width:22px; height:22px; cursor:pointer; }
      .tag { display: inline-flex; align-items: center; background-color: #2e7d32; color: #fff; padding: 4px 10px; margin: 2px; border-radius: 12px; font-size: 13px; line-height: 1; }
      .tag span { margin-left: 8px; cursor: pointer; font-weight: bold; }
      .tag span:hover { color: #ddd; }
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

  <?php if ($role === 'admin'): ?>
    <div class="form-group">
        <label>Admin Level</label>
        <input type="text" readonly
               value="<?= htmlspecialchars(ucfirst($adminData['admin_level'])) ?>"
               style="background:#f9f9f9;">
    </div>
    <div class="form-group">
        <label>Can Assign Staff</label>
        <input type="text" readonly
               value="<?= $adminData['can_assign_staff'] ? 'Yes' : 'No' ?>"
               style="background:#f9f9f9;">
    </div>
    <div class="form-group">
        <label>Can Manage Content</label>
        <input type="text" readonly
               value="<?= $adminData['can_manage_content'] ? 'Yes' : 'No' ?>"
               style="background:#f9f9f9;">
    </div>
  <?php elseif ($role === 'staff'): ?>
    <div class="form-group">
        <label>Position Title</label>
        <input type="text" readonly
               value="<?= htmlspecialchars($staffData['position_title']) ?>"
               style="background:#f9f9f9;">
    </div>
    <div class="form-group">
        <label>Assigned Tasks</label>
        <textarea readonly
                    style="background:#f9f9f9; width:100%; padding:8px; box-sizing:border-box;"><?= 
                      htmlspecialchars($staffData['assigned_tasks']) 
                    ?></textarea>
    </div>
    <div class="form-group">
        <label>Performance Score</label>
        <input type="text" readonly
                value="<?= number_format($staffData['performance_score'],2) ?>/5.00"
                style="background:#f9f9f9;">
    </div>
    <div class="form-group">
        <label>Last Review Date</label>
        <input type="text" readonly
                value="<?= date('F j, Y', strtotime($staffData['last_review_date'])) ?>"
                style="background:#f9f9f9;">
    </div>
<?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <!-- common fields -->
    <div class="form-group">
      <label>Public ID</label>
      <input type="text" value="<?= htmlspecialchars($userData['public_id']) ?>" disabled>
    </div>
    <div class="form-group">
      <label>Full Name</label>
      <input type="text" name="full_name" value="<?= htmlspecialchars($userData['full_name']) ?>" required>
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
      <input type="text" name="phone" pattern="01\d{8,9}" value="<?= htmlspecialchars($userData['phone']) ?>" required>
    </div>
    <div class="form-group">
      <label>Street</label>
      <input type="text" name="street" value="<?= htmlspecialchars($userData['street']) ?>" required>
    </div>
    <div class="form-group">
      <label>Postcode (5 digits)</label>
      <input type="text" name="postcode" pattern="\d{5}" value="<?= htmlspecialchars($userData['postcode']) ?>" required>
    </div>
    <div class="form-group">
      <label>City</label>
      <input type="text" name="city" value="<?= htmlspecialchars($userData['city']) ?>" required>
    </div>
    <div class="form-group">
      <label>State</label>
      <select name="state" required>
        <?php
        $states = ["Johor","Kedah","Kelantan","Malacca","Negeri Sembilan","Pahang","Penang","Perak","Perlis",
                   "Sabah","Sarawak","Selangor","Terengganu","Kuala Lumpur","Putrajaya","Labuan"];
        foreach ($states as $s) {
            $sel = ($userData['state'] === $s) ? "selected" : "";
            echo "<option value=\"$s\" $sel>$s</option>";
        }
        ?>
      </select>
    </div>

    <!-- vendor section -->
    <?php if ($role === 'vendor'): ?>
      <?php
        $stmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
        $stmt->execute([$id]);
        $vendor = $stmt->fetch();
        $serviceArray = array_filter(array_map('trim', explode(',', $vendor['service_listings'] ?? '')));
      ?>
      <div class="form-group">
        <label>Business Name</label>
        <input type="text"
               name="business_name"
               value="<?= htmlspecialchars($vendor['business_name'] ?? '') ?>"
               required>
      </div>
      <div class="form-group">
        <label>Website URL (optional)</label>
        <input type="url" name="website_url" value="<?= htmlspecialchars($vendor['website_url'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Service Listings (select at least 1)</label>
        <select id="service_select" onchange="addService()">
          <option value="">-- Select a service --</option>
          <option value="Livestock">Livestock</option>
          <option value="Crops">Crops</option>
          <option value="Forestry">Forestry</option>
          <option value="Dairy">Dairy</option>
          <option value="Fish farming">Fish farming</option>
          <option value="Miscellaneous">Miscellaneous</option>
        </select>

        <div id="tag_container">
          <!-- badges for already-saved services (on page load) -->
          <?php foreach ($serviceArray as $svc): ?>
            <span class="tag">
              <?= htmlspecialchars($svc) ?>
              <span onclick="removeService(this,'<?= htmlspecialchars($svc) ?>')">×</span>
            </span>
          <?php endforeach; ?>
        </div>

        <input
          type="hidden"
          name="service_listings"
          id="service_listings"
          value="<?= htmlspecialchars(implode(',', $serviceArray)) ?>"
        >

        <p style="color:red; font-size:13px;">You must select at least 1.</p>
      </div>
      <div class="form-group">
        <label>Verification Status</label>
        <input type="text" readonly
               value="<?= $vendor['is_verified'] ? 'Verified' : 'Not Verified' ?>"
               style="background:#f9f9f9;">
      </div>
      <div class="form-group">
        <label>Business Registration Number</label>
        <input type="text" name="business_reg_no" value="<?= htmlspecialchars($vendor['business_reg_no']) ?>">
      </div>
      <div class="form-group">
        <label>Upload Business Registration Document</label>
        <input type="file" name="business_doc" accept=".pdf,.jpg,.jpeg,.png">
        <p style="color:red; font-size:13px;">
          Accepted: PDF, JPG, JPEG, PNG. Max size: 2MB.
        </p>
        <?php if ($vendor['business_doc']): ?>
          <p>Uploaded:
            <a href="../uploads/<?= htmlspecialchars($vendor['business_doc']) ?>" target="_blank">View</a>
          </p>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label>Bank Account No</label>
        <input type="text" name="bank_account" pattern="\d+" maxlength="30"
               value="<?= htmlspecialchars($vendor['bank_account']) ?>" required>
        <p style="color:red; font-size:13px;">Only numbers are allowed.</p>
      </div>
      <div class="form-group">
        <label>Upload Bank Account Proof</label>
        <input type="file" name="bank_doc" accept=".pdf,.jpg,.jpeg,.png">
        <p style="color:red; font-size:13px;">
          Accepted: PDF, JPG, JPEG, PNG. Max size: 2MB.
        </p>
        <?php if ($vendor['bank_doc']): ?>
          <p>Uploaded:
            <a href="../uploads/<?= htmlspecialchars($vendor['bank_doc']) ?>" target="_blank">View</a>
          </p>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label>Subscription Tier</label>
        <input type="text" readonly
               value="<?= ucfirst($vendor['subscription_tier']) ?>"
               style="background:#f9f9f9;">
        <?php if ($vendor['subscription_tier'] === 'free'): ?>
          <p><a href="upgrade_subscription.php" style="color:green;font-weight:bold;">
            Upgrade to Premium
          </a></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- customer section -->
    <?php if ($role === 'customer'): ?>
      <?php
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE user_id = ?");
        $stmt->execute([$id]);
        $customer = $stmt->fetch();
        $selectedPayment = $customer['preferred_payment'] ?? '';
      ?>
      <div class="form-group">
        <label>Preferred Payment</label>
        <select name="preferred_payment">
          <?php
          foreach (['Credit/Debit Card','Mobile Payment','Bank Transfer'] as $opt) {
              $sel = ($selectedPayment === $opt) ? "selected" : "";
              echo "<option value=\"$opt\" $sel>$opt</option>";
          }
          ?>
        </select>
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

  // cache the select and its original options
  const serviceSelect = document.getElementById('service_select');
  const originalOptions = Array.from(serviceSelect.options)
    .filter(o => o.value)  // drop the blank
    .map(o => ({value:o.value, text:o.textContent}));

  function addService() {
    const val = serviceSelect.value;
    if (!val) return;

    // create the green badge
    const span = document.createElement('span');
    span.className = 'tag';
    span.textContent = val;

    // create the little ×
    const rm = document.createElement('span');
    rm.textContent = '×';
    rm.onclick = () => removeService(rm,val);
    span.appendChild(rm);

    document.getElementById('tag_container').appendChild(span);

    // remove that option from dropdown
    serviceSelect.querySelector(`option[value="${val}"]`).remove();

    updateServiceInput();
    serviceSelect.value = '';
  }

  function removeService(el,val) {
    // remove badge
    el.parentNode.remove();

    // restore the option
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

  // on page load: remove from the <select> any option already in $serviceArray
  document.addEventListener('DOMContentLoaded', ()=>{
    const existing = <?= json_encode($serviceArray) ?>;
    existing.forEach(val=>{
      const opt = serviceSelect.querySelector(`option[value="${val}"]`);
      opt && opt.remove();
    });
  });
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>
