<?php declare(strict_types=1);
echo '<?php'; ?>

namespace <?php echo $namespace; ?>;
<?php if (!empty($imports)) { ?>

<?php foreach ($imports as $import) { ?>
use <?php echo $import; ?>;
<?php } ?>
<?php } ?>

class <?php echo $className; ?>

{
    public function __construct(
<?php foreach ($properties as $i => $property) { ?>
        private readonly <?php echo $property['varType']; ?> $<?php echo $property['varName']; ?><?php if ($property['hasDefaultValue']) { ?> = <?php echo $property['defaultValue']; ?><?php } ?><?php echo ($i < count($properties) - 1) ? ', ' : ''; ?>

<?php } ?>
    ) {
    }
<?php foreach ($properties as $property) { ?>

    /**
     * @return <?php echo $property['paramType']; ?>

     */
    public function get<?php echo $property['methodName']; ?>()<?php echo empty($property['varType']) ? '' : ': '.$property['varType']; ?>

    {
        return $this-><?php echo $property['varName']; ?>;
    }
<?php } ?>

    public static function builder(<?php echo $className; ?> $other = null): <?php echo $builder['shortName']; ?>

    {
        return new <?php echo $builder['shortName']; ?>($other);
    }
}
