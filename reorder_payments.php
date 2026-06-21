<!-- HTML အပိုင်း -->
<ul id="payment-list" class="list-group">
    <?php foreach($payments as $payment): ?>
    <li class="list-group-item" data-id="<?= $payment['id'] ?>">
        <img src="<?= $payment['logo'] ?>" width="30"> <?= $payment['account_name'] ?>
    </li>
    <?php endforeach; ?>
</ul>

<!-- JavaScript အပိုင်း -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
const el = document.getElementById('payment-list');
const sortable = Sortable.create(el, {
    animation: 150,
    onEnd: function (evt) {
        let order = [];
        document.querySelectorAll('#payment-list li').forEach((el, index) => {
            order.push({id: el.dataset.id, sort_order: index});
        });
        // Fetch API သုံးပြီး DB မှာ update လုပ်မည့် code
        fetch('update_payment_order.php', {
            method: 'POST',
            body: JSON.stringify({order: order}),
            headers: { 'Content-Type': 'application/json' }
        });
    }
});
</script>