<?php
// /agrimarket/notification/notification.php

session_start();
require __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: /agrimarket/index.php");
    exit();
}

$userId      = $_SESSION['user']['id'];
$userType_Id = $_SESSION['user']['userType_Id'];

// ensure our “read” array exists
if (!isset($_SESSION['notif_read']) || !is_array($_SESSION['notif_read'])) {
    $_SESSION['notif_read'] = [];
}

// 1) Build dynamic notifications
$activities = [];
$promotions = [];

// — Delivered Orders —
if ($userType_Id === 4) {
    // customer: any delivered order
    $stmt = $pdo->prepare("
      SELECT id, item_description, delivered_date
        FROM sales_order
       WHERE customer_id = ?
         AND delivered_date IS NOT NULL
       ORDER BY delivered_date DESC
    ");
    $stmt->execute([$userId]);
} elseif ($userType_Id === 3) {
    // vendor: only their delivered orders
    $stmt = $pdo->prepare("
      SELECT id, item_description, delivered_date
        FROM sales_order
       WHERE vendor_id = ?
         AND delivered_date IS NOT NULL
       ORDER BY delivered_date DESC
    ");
    $stmt->execute([$userId]);
}
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $key = "order_{$row['id']}";
    $msg = $userType_Id === 4
         ? "Your order #{$row['id']} has been delivered."
         : "Order #{$row['id']} has been delivered.";
    $activities[] = [
      'key'     => $key,
      'message' => $msg,
      'time'    => $row['delivered_date']
    ];
}

// — Low Stock Alerts (vendor only) —
if ($userType_Id === 3) {
    $stmt = $pdo->prepare("
      SELECT id,name,quantity,reorder_level
        FROM product
       WHERE vendor_id = ?
         AND quantity <= reorder_level
    ");
    $stmt->execute([$userId]);
    while ($p = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $activities[] = [
          'key'     => "stock_{$p['id']}",
          'message' => "Low stock: {$p['name']} has {$p['quantity']} left (reorder ≥{$p['reorder_level']}).",
          'time'    => date('Y-m-d H:i:s')
        ];
    }
}

// — Promotion (customer only) —
if ($userType_Id === 4) {
    $promotions[] = [
      'key'     => "promo_0707",
      'message' => "🎉 20% off store-wide on July 7th! Don’t miss out.",
      'time'    => date('Y-m-d H:i:s')
    ];
}

// collect all keys
$allKeys = array_merge(
    array_column($activities, 'key'),
    array_column($promotions, 'key')
);

// 2) AJAX handlers
if (!empty($_GET['action'])) {
    header('Content-Type: application/json');
    $act = $_GET['action'];

    if ($act === 'mark_read' && !empty($_POST['key'])) {
        $_SESSION['notif_read'][] = $_POST['key'];
        $_SESSION['notif_read'] = array_unique($_SESSION['notif_read']);
        echo json_encode(['success'=>true]);
        exit;
    }
    if ($act === 'mark_all') {
        $_SESSION['notif_read'] = $allKeys;
        echo json_encode(['success'=>true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error'=>"Unknown action"]);
    exit;
}

// 3) Determine read status & count unread *per panel*
$unreadActivities = 0;
foreach ($activities as &$n) {
    $n['is_read'] = in_array($n['key'], $_SESSION['notif_read'], true);
    if (!$n['is_read']) $unreadActivities++;
}
unset($n);

$unreadPromotions = 0;
foreach ($promotions as &$n) {
    $n['is_read'] = in_array($n['key'], $_SESSION['notif_read'], true);
    if (!$n['is_read']) $unreadPromotions++;
}
unset($n);

// total unread for header dot
$unreadCount = $unreadActivities + $unreadPromotions;
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<style>
  .tab              { display:inline-block; margin-right:2rem; cursor:pointer; padding:.5rem; }
  .tab.active       { font-weight:bold; border-bottom:2px solid #333; }
  .red-dot          { display:inline-block; width:8px; height:8px; background:red; border-radius:50%; margin-left:4px; vertical-align:middle; }
  .notification-item{ padding:.75rem; border-bottom:1px solid #eee; cursor:pointer; }
  .notification-item.unread { font-weight:bold; background:#f9f9f9; }
  .time             { font-size:.8rem; color:#888; }
  #modal, #overlay  { display:none; position:fixed; }
  #overlay          { top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.4); z-index:900; }
  #modal            { top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; padding:1.5rem; box-shadow:0 2px 8px rgba(0,0,0,.2); z-index:1000; }
  #modal .close     { position:absolute; top:.5rem; right:.5rem; cursor:pointer; }
  .mark-all         { float:right; cursor:pointer; color:#007bff; font-size: 0.9rem; }
</style>

<div style="max-width:600px; margin:40px auto;">
  <h2>
    Notifications
    <?php if($unreadCount>0): ?>
      <span class="red-dot" id="hdr-dot"></span>
    <?php endif; ?>
    <span class="mark-all" id="mark-all">Mark All as Read</span>
  </h2>

  <div>
    <span class="tab active" data-tab="acts">
      Activities
      <?php if($unreadActivities>0): ?><span class="red-dot"></span><?php endif; ?>
    </span>

    <?php if ($userType_Id === 4): ?>
    <span class="tab" data-tab="proms">
      Promotions
      <?php if($unreadPromotions>0): ?><span class="red-dot"></span><?php endif; ?>
    </span>
    <?php endif; ?>
  </div>

  <div id="acts-panel">
    <?php if (empty($activities)): ?>
      <p>No activity notifications.</p>
    <?php else: foreach($activities as $n): ?>
      <div class="notification-item <?= $n['is_read'] ? '' : 'unread' ?>"
           data-key="<?= $n['key'] ?>"
           data-msg="<?= htmlspecialchars($n['message'], ENT_QUOTES) ?>">
        <div><?= htmlspecialchars($n['message']) ?></div>
        <div class="time"><?= date('M j, Y H:i', strtotime($n['time'])) ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <?php if ($userType_Id === 4): ?>
  <div id="proms-panel" style="display:none;">
    <?php if (empty($promotions)): ?>
      <p>No promotions right now.</p>
    <?php else: foreach($promotions as $n): ?>
      <div class="notification-item <?= $n['is_read'] ? '' : 'unread' ?>"
           data-key="<?= $n['key'] ?>"
           data-msg="<?= htmlspecialchars($n['message'], ENT_QUOTES) ?>">
        <div><?= htmlspecialchars($n['message']) ?></div>
        <div class="time"><?= date('M j, Y H:i', strtotime($n['time'])) ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>
  <?php endif; ?>
</div>

<div id="overlay"></div>
<div id="modal">
  <span class="close" id="close-btn">×</span>
  <div id="modal-content"></div>
</div>

<script>
(() => {
  const tabs   = document.querySelectorAll('.tab'),
        acts   = document.getElementById('acts-panel'),
        proms  = document.getElementById('proms-panel'),
        overlay= document.getElementById('overlay'),
        modal  = document.getElementById('modal'),
        mc     = document.getElementById('modal-content'),
        closeB = document.getElementById('close-btn'),
        hdrDot = document.getElementById('hdr-dot'),
        markAll= document.getElementById('mark-all');

  // Tab switching
  tabs.forEach(t => {
    t.onclick = () => {
      tabs.forEach(x => x.classList.remove('active'));
      t.classList.add('active');
      acts.style.display  = t.dataset.tab==='acts'  ? '' : 'none';
      proms.style.display = t.dataset.tab==='proms' ? '' : 'none';
    };
  });

  // Modal
  function openModal(msg){
    mc.textContent = msg;
    overlay.style.display = modal.style.display = 'block';
  }
  function closeModal(){
    overlay.style.display = modal.style.display = 'none';
  }
  closeB.onclick = overlay.onclick = closeModal;

  // Utility: remove a tab‐dot if its panel has no unread items
  function updateTabDot(panelId){
    const panelHasUnread = document.querySelector(`#${panelId}-panel .notification-item.unread`);
    const tab = document.querySelector(`.tab[data-tab="${panelId}"] .red-dot`);
    if (!panelHasUnread && tab) tab.remove();
  }

  // Single click: mark read + open modal
  document.querySelectorAll('.notification-item').forEach(div => {
    div.onclick = () => {
      const key = div.dataset.key,
            msg = div.dataset.msg;
      if (div.classList.contains('unread')) {
        fetch('?action=mark_read', {
          method: 'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body: `key=${encodeURIComponent(key)}`
        })
        .then(r=>r.json())
        .then(js=>{
          if (js.success) {
            div.classList.remove('unread');
            // update that panel's dot
            const panelId = div.closest('#proms-panel') ? 'proms' : 'acts';
            updateTabDot(panelId);
            // if *no* unread anywhere, clear header dot
            if (!document.querySelector('.notification-item.unread')) {
              const headerDot = document.querySelector('a[href="/agrimarket/notification/notification.php"] .red-dot');
              if (headerDot) headerDot.remove();
              const notifHdrDot = document.getElementById('hdr-dot');
              if (notifHdrDot) notifHdrDot.remove();
            }
          }
        });
      }
      openModal(msg);
    };
  });

  // Mark all read
  markAll.onclick = () => {
    fetch('?action=mark_all')
      .then(r=>r.json())
      .then(js=>{
        if (js.success) {
          document.querySelectorAll('.notification-item.unread')
                  .forEach(d=>d.classList.remove('unread'));
          // clear both tab dots + header dot
          document.querySelectorAll('.tab .red-dot').forEach(d=>d.remove());
          const headerDot = document.querySelector('a[href="/agrimarket/notification/notification.php"] .red-dot');
          if (headerDot) headerDot.remove();
          const notifHdrDot = document.getElementById('hdr-dot');
          if (notifHdrDot) notifHdrDot.remove();
        }
      });
  };
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
