<?php if (!defined('LEGAL_PAGE')) die; ?>
<?php require(genI18nPage('header.php')); ?>

<p>{{ @Hello World }}</p>
<p><?php echo langi18n('Hello World'); ?></p>
<p><?php echo $data['hi']; ?></p>
<p><?php echo langi18n('Hi, %1, welcome to the website developed by the %2 php framework.', 'Sweetheart', 'Doba'); ?></p>

<?php require(genI18nPage('footer.php')); ?>