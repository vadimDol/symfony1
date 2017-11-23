<?php
    /*
     *  $Id: DebugPDOStatement.php 1262 2009-10-26 20:54:39Z francois $
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

    /**
     * PDOStatement that provides some enhanced functionality needed by Propel.
     *
     * Simply adds the ability to count the number of queries executed and log the queries/method calls.
     *
     * @author     Oliver Schonrock <oliver@realtsp.com>
     * @author     Jarno Rantanen <jarno.rantanen@tkk.fi>
     * @since      2007-07-12
     * @package    propel.util
     */
    class DebugPDOStatement extends PDOStatement
    {
        const DEFAULT_SLOW_THRESHOLD = 0.1;
        const DEFAULT_ONLYSLOW_ENABLED = false;
        const DEFAULT_BINDVALUES_LOG = false;

        /**
         * Hashmap for resolving the PDO::PARAM_* class constants to their human-readable names.
         *
         * This is only used in logging the binding of variables.
         *
         * @see        self::bindValue()
         * @var        array
         */
        protected static $typeMap = array(
            PDO::PARAM_BOOL => "PDO::PARAM_BOOL",
            PDO::PARAM_INT  => "PDO::PARAM_INT",
            PDO::PARAM_STR  => "PDO::PARAM_STR",
            PDO::PARAM_LOB  => "PDO::PARAM_LOB",
            PDO::PARAM_NULL => "PDO::PARAM_NULL",
        );

        /**
         * @var array The values that have been bound
         */
        protected $boundValues = array();

        /**
         * Executes a prepared statement.  Returns a boolean value indicating success.
         *
         * Overridden for query counting and logging.
         *
         * @param array $input_parameters
         * @return bool
         */
        public function execute($input_parameters = null)
        {
            $debug  = $this->getDebugSnapshot();
            $return = parent::execute($input_parameters);

            $sql = $this->getExecutedQueryString();
            $this->log($sql, null, $debug);

            return $return;
        }

        /**
         * Binds a value to a corresponding named or question mark placeholder in the SQL statement
         * that was use to prepare the statement.  Returns a boolean value indicating success.
         *
         * @param      int   $pos   Parameter identifier (for determining what to replace in the query).
         * @param      mixed $value The value to bind to the parameter.
         * @param      int   $type  Explicit data type for the parameter using the PDO::PARAM_* constants. Defaults to PDO::PARAM_STR.
         * @return     boolean
         */
        public function bindValue($pos, $value, $type = PDO::PARAM_STR)
        {
            $debug   = $this->getDebugSnapshot();
            $typestr = isset(self::$typeMap[$type]) ? self::$typeMap[$type] : '(default)';
            $return  = parent::bindValue($pos, $value, $type);

            $valuestr = $type == PDO::PARAM_LOB ? '[LOB value]' : var_export($value, true);
            $msg      = "Binding $valuestr at position $pos w/ PDO type $typestr";

            $this->boundValues[$pos] = $valuestr;

            if (self::DEFAULT_BINDVALUES_LOG)
            {
                $this->log($msg, null, $debug);
            }

            return $return;
        }

        /**
         * @return mixed|string
         */
        private function getExecutedQueryString()
        {
            $sql = $this->queryString;

            $matches = array();
            if (preg_match_all('/(:p[0-9]+\b)/', $sql, $matches))
            {
                for ($i = 0; $i < count($matches[1]); $i++)
                {
                    $pos = $matches[1][$i];
                    $sql = str_replace($pos, $this->boundValues[$pos], $sql);
                }
            }

            return $sql;
        }

        /**
         * @return array
         */
        private function getDebugSnapshot()
        {
            return array(
                'microtime'             => microtime(true),
                'memory_get_usage'      => memory_get_usage(false),
                'memory_get_peak_usage' => memory_get_peak_usage(false),
            );
        }

        /**
         * @param string $msg
         * @param int    $level
         * @param array  $debugSnapshot
         */
        private function log($msg, $level = null, array $debugSnapshot = null)
        {
            if ($level === null)
            {
                $level = Propel::LOG_DEBUG;
            }

            $now       = $this->getDebugSnapshot();
            $queryTime = $now['microtime'] - $debugSnapshot['microtime'];

            $msg .= "\nQuery Time: " . number_format($queryTime, 3);

            // Determine if this query is slow enough to warrant logging
            if ($this->getLoggingConfig("onlyslow", self::DEFAULT_ONLYSLOW_ENABLED))
            {
                if ($queryTime < $this->getLoggingConfig("details.slow.threshold", self::DEFAULT_SLOW_THRESHOLD))
                {
                    return;
                }
            }

            if (!$msg)
            {
                return;
            }

            Propel::log($msg, $level);
        }

        /**
         * @param string $key
         * @param mixed  $defaultValue
         * @return mixed
         */
        private function getLoggingConfig($key, $defaultValue)
        {
            return Propel::getConfiguration(PropelConfiguration::TYPE_OBJECT)
                         ->getParameter("debugpdo.logging.$key", $defaultValue);
        }

    }
