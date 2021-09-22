<?php
namespace yii2swoole\webserver\helper;

/**
 * Class ConsoleTable
 * @package yii2swoole\webserver\helper
 */
class ConsoleTable
{

    /** @var array Table headers. */
    protected $headers;

    /** @var array Table rows. */
    protected $rows;

    /** @var string Resulted table string. */
    protected $output = '';

    /**
     * ConsoleTable constructor.
     *
     * @param array $header
     * @param array $rows
     */
    public function __construct(array $header = array(), array $rows = array())
    {
        $this->headers = $header;
        $this->rows = $rows;
    }

    /**
     * @param array $header
     * @param array $rows
     * @return ConsoleTable
     */
    public function create(array $header = array(), array $rows = array())
    {
        return new self($header, $rows);
    }

    /**
     * @param array $header
     */
    public function setHeader(array $header)
    {
        $this->headers = $header;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param array $rows
     */
    public function setRows(array $rows)
    {
        $this->rows = $rows;
    }

    /**
     * @return array
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * @param array $row The row data to add
     * @param bool $append Whether to append or prepend the row
     */
    public function addRow(array $row, $append = true)
    {
        if (count($this->rows)) {
            $row = array_pad($row, count($this->rows[0]), '');
        }

        if ($append) {
            $this->rows[] = $row;
        } else {
            array_unshift($this->rows, $row);
        }
    }

    /**
     * Sanitize output string to avoid bad display.
     *
     * @param string $str
     * @return string
     */
    protected function sanitize($str)
    {
        if (mb_detect_encoding((string)$str) != 'UTF-8') {
            iconv('ISO-8859-15', 'UTF-8//TRANSLIT', $str);
        }

        $str = mb_convert_encoding($str, 'HTML-ENTITIES', 'UTF-8');
        $searches = array('&szlig;', '&(..)lig;', '&([aouAOU])uml;', '&(.)[^;]*;');
        $replacements = array('ss', '\\1', '\\1e', '\\1');
        foreach ($searches as $key => $search) {
            $str = mb_ereg_replace($search, $replacements[$key], $str);
        }

        return $str;
    }

    /**
     * Convert all HTML entities to their applicable characters then uses mb_strlen if function exists else strlen.
     *
     * @param string $str
     * @param string $encoding
     * @return int
     */
    protected function strlen($str, $encoding = 'UTF-8')
    {
        $str = html_entity_decode($str, ENT_COMPAT, 'UTF-8');

        if (function_exists('mb_strlen')) {
            return mb_strlen($str, $encoding);
        }

        return strlen($str);
    }

    /**
     * @param string $header The column to check out.
     * @return int
     */
    protected function getLongestColmunSize($header)
    {
        $longest = $this->strlen($header);
        $columns = array_column($this->rows, $header);

        if (!is_array($columns) || !count($columns)) {
            // If columns are not indexed by headers name
            // Then use the normal index value (0, 1, 2, 3, 4, ...)
            $columns = array_column($this->rows, array_search($header, $this->headers));
        }

        foreach ($columns as $value) {
            $longest = max($longest, $this->strlen($this->sanitize($value)));
        }

        return $longest;
    }

    /**
     * @param bool $html If true, display with <br> otherwise with PHP_EOL
     * @return string
     */
    protected function render($html = false)
    {
        $display_header = true;
        $line_format = '|';
        $line_separator = '+';
        $total_line_length = 0;

        if (!count($this->headers)) {
            $display_header = false;

            $this->headers = count($this->rows) ? $this->rows[0] : [];
        }

        foreach ($this->headers as $header) {
            $len = (int)$this->getLongestColmunSize($header);
            $line_format .= ' %-'.$len.'s |';
            $line_separator .= '-'.str_repeat('-', $len).'-+';
            $total_line_length += 3 + $len;
        }

        $line_format .= $html ? '<br />' : PHP_EOL;
        $line_separator .= $html ? '<br />' : PHP_EOL;

        $this->output .= $line_separator;

        if ($display_header) {
            $this->output .= vsprintf($line_format, $this->headers);
            $this->output .= $line_separator;
        }

        foreach ($this->rows as $row) {
            $this->output .= vsprintf($line_format, array_map([$this, 'sanitize'], $row));
        }

        $this->output .= $line_separator;

        return $this->output;
    }

    /**
     * Fetch the resulted table as a string.
     *
     * @param bool $html If true, display with <br> otherwise with PHP_EOL
     * @return string
     */
    public function fetch($html = false)
    {
        return $this->render($html);
    }

    /**
     * Display the resulted table.
     *
     * @param bool $html If true, display with <br> otherwise with PHP_EOL
     */
    public function display($html = false)
    {
        echo $this->render($html);
    }
}