<form method="post" name="rule" action="<?php echo Horde::url('rule.php') ?>">
<?php Horde_Util::pformInput() ?>
<input type="hidden" name="actionID" value="rule_update" />
<input type="hidden" name="conditionnumber" value="-1" />
<?php if (isset($vars->edit)): ?>
<input type="hidden" name="edit" value="<?php echo $vars->edit ?>" />
<?php endif; ?>
<?php if (isset($rule['id'])): ?>
<input type="hidden" name="id" value="<?php echo $rule['id'] ?>" />
<?php endif; ?>

<div class="header">
 <?php echo _("Filter Rule") ?>
 <?php if (!empty($rule['disable'])): ?>[<span class="horde-form-error"><?php echo _("Disabled") ?></span>]<?php endif; ?>
 <?php echo Horde_Help::link('ingo', 'rule') ?>
</div>

<table>
  <tr class="control">
    <td>
      <?php echo Horde::label('name', _("Rule Name:")) ?>
      <input class="input" id="name" name="name" size="50" value="<?php echo (isset($rule['name'])) ? htmlspecialchars($rule['name']) : '' ?>" />
    </td>
    <td>
      <?php echo Horde_Help::link('ingo', 'rule-name') ?>&nbsp;
    </td>
  </tr>

  <tr>
    <td colspan="2">
      <em><?php echo _("For an incoming message that matches:") ?></em>
    </td>
  </tr>

  <tr>
    <td>
      <input id="all" onclick="document.rule.submit(); return true;" type="radio" name="combine" value="<?php echo Ingo_Storage::COMBINE_ALL ?>"<?php echo ($rule['combine'] == Ingo_Storage::COMBINE_ALL) ? ' checked="checked"' : '' ?> /><?php echo Horde::label('all', _("All of the following")) ?>&nbsp;
      <input id="any" onclick="document.rule.submit(); return true;" type="radio" name="combine" value="<?php echo Ingo_Storage::COMBINE_ANY ?>"<?php echo ($rule['combine'] == Ingo_Storage::COMBINE_ANY) ? ' checked="checked"' : '' ?> /><?php echo Horde::label('any', _("Any of the following")) ?>
    </td>
    <td>
      <?php echo Horde_Help::link('ingo', 'rule-combine') ?>&nbsp;
    </td>
  </tr>

  <tr>
    <td>
      <table>
