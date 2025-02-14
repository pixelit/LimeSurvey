<?php
/**
 * @var int $lid
 * @var array $labelsets
 * @var array $labelbar
 */
?>
<?php
    App()->getClientScript()->registerScriptFile(
        App()->getConfig('adminscripts') . 'topbar.js',
        CClientScript::POS_END
    );
?>

<script type="text/javascript">
    var sImageURL = ''; // in 2.06, used to display the icon in jQgrid, not necessary anymore
    var duplicatelabelcode = '<?php eT('Error: You are trying to use duplicate label codes.', 'js'); ?>';
    var otherisreserved = '<?php eT("Error: 'other' is a reserved keyword.", 'js'); ?>';
    var quickaddtitle = '<?php eT('Quick-add subquestion or answer items', 'js'); ?>';
</script>

<!-- Label Bar menu -->
<div class='menubar surveybar' id="labelbar">
    <div class='row'>

        <div class="col-lg-6">

            <!-- View buttons -->
            <?php if (isset($labelbar['buttons']['view'])) : ?>
                <?php if (Permission::model()->hasGlobalPermission('labelsets', 'create') || Permission::model()->hasGlobalPermission('labelsets', 'import')) : ?>
                    <!-- Create or Import -->
                    <a class="btn btn-default" href="<?php echo $this->createUrl("admin/labels/sa/newlabelset"); ?>" role="button">
                        <span class="icon-add text-success"></span>
                        <?php eT("Create or import new label set(s)"); ?>
                    </a>
                <?php endif; ?>

            <?php endif; ?>


            <!-- Edition buttons -->

            <?php if (isset($labelbar['buttons']['edition'])) : ?>
                <?php if (isset($labelbar['buttons']['edit'])) : ?>
                    <!-- Edit label set -->
                    <?php if (Permission::model()->hasGlobalPermission('labelsets', 'update')) : ?>
                        <a class="btn btn-default" href="<?php echo $this->createUrl("admin/labels/sa/editlabelset/lid/" . $lid); ?>" role="button">
                            <span class="fa fa-pencil  text-success"></span>
                            <?php eT("Edit label set"); ?>
                        </a>
                    <?php endif; ?>


                    <!-- Export this label set -->
                    <?php if (Permission::model()->hasGlobalPermission('labelsets', 'export')) : ?>
                        <a class="btn btn-default" href="<?php echo $this->createUrl("admin/export/sa/dumplabel/lid/$lid"); ?>"
                           role="button">
                            <span class="icon-export text-success"></span>
                            <?php eT("Export this label set"); ?>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>


                <!-- Delete label set -->
                <?php if (isset($labelbar['buttons']['delete']) && $labelbar['buttons']['delete'] == true) : ?>
                    <?php if (Permission::model()->hasGlobalPermission('labelsets', 'delete')) : ?>
                        <button class="btn btn-default"
                                data-toggle="modal"
                                data-title="<?= gt('Delete label set') ?>"
                                data-target="#confirmation-modal"
                                data-btnclass='btn-danger'
                                data-btntext="<?= gt('Delete') ?>"
                                data-onclick='(function() { <?php echo convertGETtoPOST(Yii::app()->createUrl("admin/labels/sa/delete/", ["lid" => $lid])); ?> })'
                                data-message="<?php eT("Do you really want to delete this label set?", "js"); ?>">
                            <span class="fa fa-trash text-danger"></span>
                            <?php eT("Delete label set"); ?>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>

            <?php endif; ?>

        </div>

        <!-- Right action buttons -->
        <div class="col-lg-6 text-right">
            <!-- Export Multiple -->
            <?php if (count($labelsets) > 0) : ?>
                <?php if (Permission::model()->hasGlobalPermission('labelsets', 'export')) : ?>
                    <a class="btn btn-default"
                       href="<?php echo $this->createUrl("admin/labels/sa/exportmulti"); ?>"
                       role="button">
                        <span class="icon-export text-success"></span>
                        <?php eT("Export"); ?>
                    </a>
                <?php endif; ?>
            <?php else : ?>
                <?php if (Permission::model()->hasGlobalPermission('labelsets', 'export')) : ?>
                    <span title="<?php eT("No label sets available"); ?>" data-toggle="tooltip" data-placement="bottom" style="display: inline-block">
                            <a class="btn btn-default disabled" role="button">
                                <span class="icon-export text-success"></span>
                                <?php eT("Export multiple label sets"); ?>
                            </a>
                        </span>
                <?php endif; ?>
            <?php endif; ?>

            <!-- return to admin panel -->
            <?php if (isset($labelbar['buttons']['return'])) : ?>
                <a class="btn btn-default" href="<?php echo $this->createUrl('admin/index'); ?>" role="button">
                    <span class="fa fa-backward"></span>
                    &nbsp;&nbsp;
                    <?php eT('Back'); ?>
                </a>
            <?php endif; ?>

            <!-- Close -->
            <?php if (isset($labelbar['closebutton']['url'])) : ?>
                <a class="btn btn-danger" href="<?php echo $labelbar['closebutton']['url']; ?>" role="button">
                    <span class="fa fa-close"></span>
                    <?php eT("Close"); ?>
                </a>
            <?php endif; ?>

            <!-- White Close button -->
            <?php if (isset($labelbar['white_closebutton']['url'])) : ?>
                <a class="btn btn-default" href="<?php echo $labelbar['white_closebutton']['url']; ?>" role="button">
                    <span class="fa fa-close"></span>
                    <?php eT("Close"); ?>
                </a>
            <?php endif; ?>

            <!-- Save and Close -->
            <?php if (isset($labelbar['saveandclosebutton']['form'])) : ?>
                <a class="btn btn-default" href="#" role="button" id="save-and-close-form-button" onclick="$(this).addClass('disabled').attr('onclick', 'return false;');" data-form-id="<?php echo $labelbar['saveandclosebutton']['form']; ?>">
                    <span class="fa fa-saved"></span>
                    <?php eT("Save and close"); ?>
                </a>
            <?php endif; ?>

            <?php if (isset($labelbar['buttons']['edition'])) : ?>
                <!-- Save -->
                <a class="btn btn-success"
                   href="#"
                   role="button"
                   id="save-form-button"
                   data-form-id="<?php echo $labelbar['savebutton']['form']; ?>"
                >
                    <span class="fa fa-check"></span>
                    <?php echo $labelbar['savebutton']['text']; ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
