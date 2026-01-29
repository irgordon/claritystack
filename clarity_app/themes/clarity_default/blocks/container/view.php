<?php
$bgColor = $background_color ?? 'bg-gray-100'; // e.g. bg-white, bg-gray-50
?>

<div class="<?= htmlspecialchars($bgColor) ?> py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <?php if (!empty($title)): ?>
            <div class="text-center mb-12">
                <h2 class="text-3xl font-extrabold text-gray-900"><?= htmlspecialchars($title) ?></h2>
            </div>
        <?php endif; ?>

        <div class="space-y-12">
            [blocks-container]
        </div>
    </div>
</div>
