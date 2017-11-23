<?php

/*
 *  $Id: PHP5MapBuilderBuilder.php 1159 2009-09-22 15:24:20Z francois $
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information please see
 * <http://propel.phpdb.org>.
 */

require_once 'propel/engine/builder/om/OMBuilder.php';

/**
 * Generates the PHP5 table map class for user object model (OM).
 *
 * @author     Hans Lellelid <hans@xmpl.org>
 * @package    propel.engine.builder.om.php5
 */
class PHP5TableMapBuilder extends OMBuilder
{
    /**
     * Gets the package for the map builder classes.
     * @return     string
     */
    public function getPackage()
    {
        return parent::getPackage() . '.map';
    }

    /**
     * Returns the name of the current class being built.
     * @return     string
     */
    public function getUnprefixedClassname()
    {
        return $this->getTable()->getPhpName() . 'TableMap';
    }

    /**
     * Adds the include() statements for files that this class depends on or utilizes.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addIncludes(&$script)
    {
    } // addIncludes()

    /**
     * Adds class phpdoc comment and openning of class.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addClassOpen(&$script)
    {
        $script .= "
class " . $this->getClassname() . " extends TableMap
{";
    }

    /**
     * Specifies the methods that are added as part of the map builder class.
     * This can be overridden by subclasses that wish to add more methods.
     * @see        ObjectBuilder::addClassBody()
     */
    protected function addClassBody(&$script)
    {
        $this->addConstants($script);
        $this->addAttributes($script);
        $this->addInitialize($script);
        $this->addBuildRelations($script);
        $this->addGetBehaviors($script);
    }

    /**
     * Adds any constants needed for this TableMap class.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addConstants(&$script)
    {
        $script .= "
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = '" . $this->getClasspath() . "';
";
    }

    /**
     * Adds any attributes needed for this TableMap class.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addAttributes(&$script)
    {
    }

    /**
     * Closes class.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addClassClose(&$script)
    {
        $script .= "
}";
        $this->applyBehaviorModifier('tableMapFilter', $script, "");
    }

    /**
     * Adds the addInitialize() method to the  table map class.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addInitialize(&$script)
    {

        $table      = $this->getTable();
        $platform   = $this->getPlatform();
        $ddlBuilder = $this->getDDLBuilder();

        $script .= "
    /**
     * Initialize the table attributes, columns and validators
     * Relations are not initialized by this method since they are lazy loaded
     *
     * @return     void
     * @throws     PropelException
     */
    public function initialize()
    {
        // attributes
        \$this->setName('" . $table->getName() . "');
        \$this->setPhpName('" . $table->getPhpName() . "');
        \$this->setClassname('" . $this->getObjectClassname() . "');
        \$this->setPackage('" . parent::getPackage() . "');";
        if ($table->getIdMethod() == "native")
        {
            $script .= "
        \$this->setUseIdGenerator(true);";
        }
        else
        {
            $script .= "
        \$this->setUseIdGenerator(false);";
        }

        if ($table->getIdMethodParameters())
        {
            $params = $table->getIdMethodParameters();
            $imp    = $params[0];
            $script .= "
        \$this->setPrimaryKeyMethodInfo('" . $imp->getValue() . "');";
        }
        elseif ($table->getIdMethod() == IDMethod::NATIVE && ($platform->getNativeIdMethod() == Platform::SEQUENCE || $platform->getNativeIdMethod() == Platform::SERIAL))
        {
            $script .= "
        \$this->setPrimaryKeyMethodInfo('" . $this->prefixTablename($ddlBuilder->getSequenceName()) . "');";
        }

        // Add columns to map
        $script .= "
        // columns";
        foreach ($table->getColumns() as $col)
        {
            $cup = strtoupper($col->getName());
            $cfc = $col->getPhpName();
            if (!$col->getSize())
            {
                $size = "null";
            }
            else
            {
                $size = $col->getSize();
            }
            $default = $col->getDefaultValueString();
            if ($col->isPrimaryKey())
            {
                if ($col->isForeignKey())
                {
                    foreach ($col->getForeignKeys() as $fk)
                    {
                        $script .= "
        \$this->addForeignPrimaryKey('$cup', '$cfc', '" . $col->getType() . "' , '" . $fk->getForeignTableName() . "', '" . strtoupper($fk->getMappedForeignColumn($col->getName())) . "', " . ($col->isNotNull() ? 'true' : 'false') . ", " . $size . ", $default);";
                    }
                }
                else
                {
                    $script .= "
        \$this->addPrimaryKey('$cup', '$cfc', '" . $col->getType() . "', " . var_export($col->isNotNull(), true) . ", " . $size . ", $default);";
                }
            }
            else
            {
                if ($col->isForeignKey())
                {
                    foreach ($col->getForeignKeys() as $fk)
                    {
                        $script .= "
        \$this->addForeignKey('$cup', '$cfc', '" . $col->getType() . "', '" . $fk->getForeignTableName() . "', '" . strtoupper($fk->getMappedForeignColumn($col->getName())) . "', " . ($col->isNotNull() ? 'true' : 'false') . ", " . $size . ", $default);";
                    }
                }
                else
                {
                    $script .= "
        \$this->addColumn('$cup', '$cfc', '" . $col->getType() . "', " . var_export($col->isNotNull(), true) . ", " . $size . ", $default);";
                }
            } // if col-is prim key
        } // foreach

        // validators
        $script .= "
        // validators";
        foreach ($table->getValidators() as $val)
        {
            $col = $val->getColumn();
            $cup = strtoupper($col->getName());
            foreach ($val->getRules() as $rule)
            {
                if ($val->getTranslate() !== Validator::TRANSLATE_NONE)
                {
                    $script .= "
        \$this->addValidator('$cup', '" . $rule->getName() . "', '" . $rule->getClass() . "', '" . str_replace("'", "\'", $rule->getValue()) . "', " . $val->getTranslate() . "('" . str_replace("'", "\'", $rule->getMessage()) . "'));";
                }
                else
                {
                    $script .= "
        \$this->addValidator('$cup', '" . $rule->getName() . "', '" . $rule->getClass() . "', '" . str_replace("'", "\'", $rule->getValue()) . "', '" . str_replace("'", "\'", $rule->getMessage()) . "');";
                } // if ($rule->getTranslation() ...
            } // foreach rule
        }  // foreach validator

        $script .= "
    }
";

    }

    /**
     * Adds the method that build the RelationMap objects
     * @param      string &$script The script will be modified in this method.
     */
    protected function addBuildRelations(&$script)
    {
        $script .= "
    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {";
        foreach ($this->getTable()->getForeignKeys() as $fkey)
        {
            $columnMapping = 'array(';
            foreach ($fkey->getLocalForeignMapping() as $key => $value)
            {
                $columnMapping .= "'$key' => '$value', ";
            }
            $columnMapping .= ')';
            $onDelete = $fkey->hasOnDelete() ? "'" . $fkey->getOnDelete() . "'" : 'null';
            $onUpdate = $fkey->hasOnUpdate() ? "'" . $fkey->getOnUpdate() . "'" : 'null';
            $script .= "
        \$this->addRelation('" . $this->getFKPhpNameAffix($fkey) . "', '" . $fkey->getForeignTable()->getPhpName() . "', RelationMap::MANY_TO_ONE, $columnMapping, $onDelete, $onUpdate);";
        }
        foreach ($this->getTable()->getReferrers() as $fkey)
        {
            $columnMapping = 'array(';
            foreach ($fkey->getForeignLocalMapping() as $key => $value)
            {
                $columnMapping .= "'$key' => '$value', ";
            }
            $columnMapping .= ')';
            $onDelete = $fkey->hasOnDelete() ? "'" . $fkey->getOnDelete() . "'" : 'null';
            $onUpdate = $fkey->hasOnUpdate() ? "'" . $fkey->getOnUpdate() . "'" : 'null';
            $script .= "
        \$this->addRelation('" . $this->getRefFKPhpNameAffix($fkey) . "', '" . $fkey->getTable()->getPhpName() . "', RelationMap::ONE_TO_" . ($fkey->isLocalPrimaryKey() ? "ONE" : "MANY") . ", $columnMapping, $onDelete, $onUpdate);";
        }
        $script .= "
    }
";
    }

    /**
     * Adds the behaviors getter
     * @param      string &$script The script will be modified in this method.
     */
    protected function addGetBehaviors(&$script)
    {
        if ($behaviors = $this->getTable()->getBehaviors())
        {
            $script .= "
    /**
     *
     * Gets the list of behaviors registered for this table
     *
     * @return array Associative array (name => parameters) of behaviors
     */
    public function getBehaviors()
    {
        return array(";
            foreach ($behaviors as $behavior)
            {
                $script .= "
            '{$behavior->getName()}' => array(";
                foreach ($behavior->getParameters() as $key => $value)
                {
                    $script .= "'$key' => '$value', ";
                }
                $script .= "),";
            }
            $script .= "
        );
    }";
        }
    }

    /**
     * Checks whether any registered behavior on that table has a modifier for a hook
     * @param string $hookName The name of the hook as called from one of this class methods, e.g. "preSave"
     * @return boolean
     */
    public function hasBehaviorModifier($hookName)
    {
        return parent::hasBehaviorModifier($hookName, 'TableMapBuilderModifier');
    }

    /**
     * Checks whether any registered behavior on that table has a modifier for a hook
     * @param string $hookName The name of the hook as called from one of this class methods, e.g. "preSave"
     * @param string &$script The script will be modified in this method.
     */
    public function applyBehaviorModifier($hookName, &$script, $tab = "        ")
    {
        return parent::applyBehaviorModifier($hookName, 'TableMapBuilderModifier', $script, $tab);
    }
}