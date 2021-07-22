<?php echo '<?php'; ?>

namespace <?php echo $namespace; ?>;
<?php if (!empty($imports)) { ?>

<?php foreach ($imports as $import) { ?>
use <?php echo $import; ?>;
<?php } ?>
<?php } ?>

class <?php echo $className; ?>

{
<?php foreach ($properties as $property) { ?>
    /**
     * @var <?php echo $property['paramType']; ?>

     */
    private $<?php echo $property['varName']; ?><?php if ($property['hasDefaultValue']) { ?> = <?php echo $property['defaultValue']; ?><?php }?>;
<?php } ?>

    public function __construct(?<?php echo $targetClass; ?> $value = null)
    {
        if ($value !== null) {
<?php foreach ($properties as $property) { ?>
            $this-><?php echo $property['varName']; ?> = $value->get<?php echo $property['methodName']; ?>();
<?php } ?>
        }
    }
<?php foreach ($properties as $property) { ?>

    /**
     * @return <?php echo $property['paramType']; ?>

     */
    public function get<?php echo $property['methodName']; ?>()<?php echo empty($property['varType']) ? '' : ' :'.$property['varType']; ?>

    {
        return $this-><?php echo $property['varName']; ?>;
    }

    /**
     * @param <?php echo $property['targetParamType']; ?> $<?php echo $property['varName']; ?>

     * @return self
     */
    public function set<?php echo $property['methodName']; ?>(<?php echo empty($property['targetVarType']) ? '' : $property['targetVarType'].' '; ?>$<?php echo $property['varName']; ?>): self
    {
        $this-><?php echo $property['varName']; ?> = $<?php echo $property['varName']; ?>;
        return $this;
    }
<?php } ?>

    public function build(): <?php echo $targetClass; ?>

    {
        return new <?php echo $targetClass; ?>(<?php foreach ($properties as $i => $property) { ?>$this-><?php echo $property['varName']; ?><?php echo ($i < count($properties) - 1) ? ', ' : ''; ?><?php } ?>);
    }
}
