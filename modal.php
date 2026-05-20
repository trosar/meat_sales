<?php
// IMPORTANT: Set $modal_message BEFORE including this file
// Usage: < ?php $modal_message = "Sure?"; $modal_confirm_btn = "Yes"; $modal_cancel_btn = "No"; include 'modal.php'; ? >

$modal_message = $modal_message ?? "Are you sure?"; // Default message if not set
$modal_confirm_btn = $modal_confirm_btn ?? "Yes";
$modal_cancel_btn = $modal_cancel_btn ?? "Cancel";
?>

<div id="custom-modal" style="display:none;" class="modal-overlay">
    <div class="modal-content">
        <p class="modal-text"><?php echo $modal_message; ?></p>
        <button id="modal-confirm" class="modal-btn modal-btn-confirm"><?php echo $modal_confirm_btn; ?></button>
        <button id="modal-cancel"  class="modal-btn modal-btn-cancel"><?php echo $modal_cancel_btn; ?></button>
    </div>
</div>
