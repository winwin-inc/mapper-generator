<?php echo '<?php'; ?>

namespace <?php echo $namespace; ?>;

class <?php echo $className; ?>

{
<?php foreach ($properties as $property) { ?>
    /**
     * @var <?php echo $property['paramType']; ?>

     */
    private $<?php echo $property['varName']; ?>;
<?php } ?>
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

     */
    public function set<?php echo $property['methodName']; ?>(<?php echo empty($property['targetVarType']) ? '' : $property['targetVarType'].' '; ?>$<?php echo $property['varName']; ?>): self

    {
        $this-><?php echo $property['varName']; ?> = $<?php echo $property['varName']; ?>;
        return $this;
    }
<?php } ?>

    public function build(): <?php echo $targetClass; ?>

    {
        return new <?php echo $targetClass; ?>(<?php foreach ($properties as $i => $property) { ?>$this-><?php echo $property['varName']; ?><?php if ($i < count($properties) - 1) { ?>, <?php } else { ?><?php } ?><?php } ?>);
    }
}
