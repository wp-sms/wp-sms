<div class="c-form__footer u-flex-end">
    <?php
    if (!empty($ctas)) {
        foreach ($ctas as $cta) {
            ?>
            <a href="<?= $cta['url'] ?>" class="c-btn c-btn--primary"><?= $cta['text'] ?></a>
            <?php
        }
    }
    ?>
</div>
</div>
</section>
<section class="c-section--nextstep u-text-center">
    <a class="c-link" href="#" title="Skip setup WP SMS">Skip setup WP SMS</a>
</section>
</div>
<script src="/scripts/main.min.js"></script>
</body>
</html>
