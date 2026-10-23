<?php
/**
 * The texts of 7x SQLI Import that are translated, collected from the code: what translations/<locale>/translation.ts
 * has to hold. Used by tests/unit/TranslationsTest.php and by the completeness report of a translation.
 *
 *   templates   every 'text'|i18n( 'context' ) of the templates under design/
 *   php         every ezpI18n::tr( 'context', 'text' ), SQLIImportUtils::translate( 'context', 'text' ) and the
 *               wrappers of a class with a fixed context (expImportStatistics::tr, expImportOptionSchema::tr)
 *   labels      the words of the codes the tables store (expImportAdmin::labels(), context extension/sqliimport/<group>)
 *   settings    the names menu.ini gives the tab (its TranslationContext) and the navigation part (kernel/navigationpart),
 *               and the transfer tool of kernelservices.ini (kernel/transfer: name, description, action labels)
 *   catalogue   what the catalogue and the editor show of a format, a handler and a transform: label, description,
 *               notice, availability, example labels (extension/sqliimport/catalogue), and the label, help and enum
 *               value labels of every option schema (extension/sqliimport/options)
 *   dynamic     texts translated with a variable (the 1.x status strings, the metrics of a comparison)
 *
 * Contexts the kernel's own translation file holds (the width switch of a left menu, the rich text parser) are left out.
 */
class SqliImportTranslationStrings
{
    /** contexts the kernel translates in share/translations: the extension's file need not repeat them */
    const KERNEL_CONTEXTS = array( 'design/admin/parts/user/menu', 'design/standard/ezoe/handler', 'kernel/classes/datatypes/ezxmltext' );

    /** contexts of the wrappers of a class: file (relative to the extension) => array( call, context ) */
    const WRAPPERS = array(
        'classes/exp/expimportstatistics.php' => array( 'self::tr', 'extension/sqliimport/statistics' ),
        'classes/exp/expimportoptionschema.php' => array( 'self::tr', 'extension/sqliimport/options' ),
        'classes/exp/expimportmapper.php' => array( 'self::tr', 'extension/sqliimport/mapping' ),
    );

    /**
     * The calls that translate a variable on purpose: file => what the variable is (its texts come from labels(),
     * catalogue() or dynamic()). A new call of this kind has to be listed here and its texts added there.
     */
    const DYNAMIC_CALLS = array(
        'classes/exp/expimport.php' => 'the wrapper expImport::tr() itself',
        'classes/exp/expimportadmin.php' => 'expImportAdmin::labels()',
        'classes/exp/expimportmapper.php' => 'its wrapper expImportMapper::tr()',
        'classes/exp/expimportlegacy.php' => 'the status string of a 1.x import request (dynamic())',
        'classes/exp/expimportstatistics.php' => 'its wrapper and the metrics of compare() (dynamic())',
        'classes/exp/expimportoptionschema.php' => 'the label of an option (catalogue())',
        'classes/sqliimportutils.php' => 'the wrapper SQLIImportUtils::translate() itself',
        'modules/sqliimport/action.php' => 'the status string of a 1.x import request (dynamic())',
        'modules/sqliimport/alterimport.php' => 'the status string of a 1.x import request (dynamic())',
    );

    /** @var string the root of the extension */
    protected $root;

    /** @var array context => source => where (file:line, or what the text is) */
    protected $texts = array();

    /** @var string[] calls whose context or text is not a literal (and is not covered by labels or dynamic texts) */
    public $nonLiteral = array();

    public function __construct( $root = null )
    {
        $this->root = rtrim( $root !== null ? $root : dirname( __DIR__ ), '/' );
    }

    /** @return array context => source => where, sorted */
    public function collect()
    {
        $this->texts = array();
        $this->nonLiteral = array();
        $this->templates();
        $this->php();
        $this->labels();
        $this->settings();
        $this->catalogue();
        $this->dynamic();
        foreach ( self::KERNEL_CONTEXTS as $context )
            unset( $this->texts[$context] );
        ksort( $this->texts );
        return $this->texts;
    }

    protected function add( $context, $source, $where )
    {
        $source = (string)$source;
        if ( trim( $source ) === '' || !preg_match( '/\pL/u', $source ) )
            return;
        if ( !isset( $this->texts[$context][$source] ) )
            $this->texts[$context][$source] = $where;
    }

    /** @return string[] the files under a directory of the extension with an extension */
    protected function files( $dir, $suffix )
    {
        $out = array();
        if ( !is_dir( $this->root . '/' . $dir ) )
            return $out;
        $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->root . '/' . $dir, FilesystemIterator::SKIP_DOTS ) );
        foreach ( $it as $file )
            if ( substr( $file->getFilename(), -strlen( $suffix ) ) === $suffix )
                $out[] = $file->getPathname();
        sort( $out );
        return $out;
    }

    protected function relative( $path )
    {
        return substr( $path, strlen( $this->root ) + 1 );
    }

    protected function templates()
    {
        foreach ( $this->files( 'design', '.tpl' ) as $file )
        {
            $code = (string)file_get_contents( $file );
            if ( strpos( $code, 'i18n(' ) === false )
                continue;
            preg_match_all( "/'((?:[^'\\\\\\n]|\\\\.){0,800})'\\s*\\|\\s*i18n\\(\\s*'([^'\\n]{1,200})'/", $code, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
            foreach ( $m as $match )
            {
                $line = substr_count( substr( $code, 0, $match[0][1] ), "\n" ) + 1;
                // as the template engine reads the text: \' is a quote, \} and \{ a brace
                $this->add( $match[2][0], preg_replace( "/\\\\(['{}\\\\])/", '$1', $match[1][0] ), $this->relative( $file ) . ':' . $line );
            }
            // an i18n on anything but a literal text
            if ( preg_match_all( "/[^'\\s]\\s*\\|\\s*i18n\\(\\s*'([^'\\n]{1,200})'/", $code, $d, PREG_OFFSET_CAPTURE ) )
                foreach ( $d[1] as $context )
                    if ( !in_array( $context[0], array( 'extension/sqliimport/catalogue', 'extension/sqliimport/options' ), true ) )
                        $this->nonLiteral[] = $this->relative( $file ) . ':' . ( substr_count( substr( $code, 0, $context[1] ), "\n" ) + 1 ) . ' i18n of a variable in ' . $context[0];
        }
    }

    /** @return string|null the value of a literal PHP string token, null for anything else */
    protected static function literal( $token )
    {
        if ( !is_array( $token ) || $token[0] !== T_CONSTANT_ENCAPSED_STRING )
            return null;
        $text = $token[1];
        $quote = $text[0];
        $body = substr( $text, 1, -1 );
        if ( $quote === "'" )
            return str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $body );
        return stripcslashes( $body );
    }

    /**
     * The arguments of a call: list of token lists, from the token after the opening parenthesis.
     *
     * @return array list of token lists (whitespace and comments dropped)
     */
    protected static function arguments( array $tokens, $i )
    {
        $args = array( array() );
        $depth = 0;
        for ( $n = count( $tokens ); $i < $n; $i++ )
        {
            $t = $tokens[$i];
            if ( is_array( $t ) && in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) )
                continue;
            if ( $t === '(' || $t === '[' || $t === '{' )
                $depth++;
            elseif ( $t === ')' || $t === ']' || $t === '}' )
            {
                if ( $depth === 0 )
                    break;
                $depth--;
            }
            elseif ( $t === ',' && $depth === 0 )
            {
                $args[] = array();
                continue;
            }
            $args[count( $args ) - 1][] = $t;
        }
        return $args;
    }

    protected function php()
    {
        $files = array();
        foreach ( array( 'classes', 'modules', 'bin', 'cronjobs', 'update' ) as $dir )
            $files = array_merge( $files, $this->files( $dir, '.php' ) );
        foreach ( $files as $file )
        {
            $code = (string)file_get_contents( $file );
            $rel = $this->relative( $file );
            $wrapper = isset( self::WRAPPERS[$rel] ) ? self::WRAPPERS[$rel] : null;
            if ( !preg_match( '/ezpI18n::tr|SQLIImportUtils::translate|expImport::tr/', $code ) && !$wrapper )
                continue;
            $tokens = token_get_all( $code );
            $n = count( $tokens );
            for ( $i = 0; $i < $n; $i++ )
            {
                $t = $tokens[$i];
                if ( !is_array( $t ) || !in_array( $t[0], array( T_STRING, T_STATIC ), true ) )
                    continue;
                $name = strtolower( $t[1] );
                if ( !in_array( $name, array( 'ezpi18n', 'sqliimportutils', 'expimport', 'self' ), true ) )
                    continue;
                $j = $i + 1;
                if ( !isset( $tokens[$j + 2] ) || !is_array( $tokens[$j] ) || $tokens[$j][0] !== T_DOUBLE_COLON )
                    continue;
                $method = is_array( $tokens[$j + 1] ) ? strtolower( $tokens[$j + 1][1] ) : '';
                $call = $name . '::' . $method;
                $fixed = null;
                if ( $call === 'self::tr' && $wrapper && $wrapper[0] === 'self::tr' )
                    $fixed = $wrapper[1];
                elseif ( !in_array( $call, array( 'ezpi18n::tr', 'sqliimportutils::translate', 'expimport::tr' ), true ) )
                    continue;
                $k = $j + 2;
                while ( isset( $tokens[$k] ) && is_array( $tokens[$k] ) && $tokens[$k][0] === T_WHITESPACE )
                    $k++;
                if ( !isset( $tokens[$k] ) || $tokens[$k] !== '(' )
                    continue;
                // the declaration of the wrapper itself
                if ( isset( $tokens[$i - 2] ) && is_array( $tokens[$i - 2] ) && $tokens[$i - 2][0] === T_FUNCTION )
                    continue;
                $args = self::arguments( $tokens, $k + 1 );
                $line = $t[2];
                if ( $fixed !== null )
                {
                    $context = $fixed;
                    $textTokens = $args[0];
                }
                else
                {
                    $context = count( $args[0] ) === 1 ? self::literal( $args[0][0] ) : null;
                    $textTokens = isset( $args[1] ) ? $args[1] : array();
                }
                $text = count( $textTokens ) === 1 ? self::literal( $textTokens[0] ) : null;
                if ( $context === null || $text === null )
                {
                    if ( !isset( self::DYNAMIC_CALLS[$rel] ) )
                        $this->nonLiteral[] = "$rel:$line $call with a context or text that is not a literal";
                    continue;
                }
                $this->add( $context, $text, "$rel:$line" );
            }
        }
    }

    protected function labels()
    {
        if ( !class_exists( 'expImportAdmin' ) || !method_exists( 'expImportAdmin', 'labels' ) )
            return;
        foreach ( expImportAdmin::labels() as $group => $labels )
            foreach ( $labels as $code => $label )
                $this->add( 'extension/sqliimport/' . $group, $label, "expImportAdmin::labels() $group.$code" );
    }

    /** @return array section => name => value (a [] list as an array) of an INI file of the extension's settings */
    protected function ini( $name )
    {
        $file = $this->root . '/settings/' . $name;
        $out = array();
        $section = '';
        if ( !is_file( $file ) )
            return $out;
        foreach ( preg_split( '/\r\n|\n/', (string)file_get_contents( $file ) ) as $line )
        {
            $line = trim( $line );
            if ( $line === '' || $line[0] === '#' || strpos( $line, '<?php' ) === 0 || strpos( $line, '*/' ) === 0 )
                continue;
            if ( preg_match( '/^\[(.+)\]$/', $line, $m ) )
            {
                $section = $m[1];
                continue;
            }
            if ( !preg_match( '/^([A-Za-z0-9_]+)(\[([^\]]*)\])?=(.*)$/', $line, $m ) )
                continue;
            if ( $m[2] !== '' )
            {
                if ( $m[3] === '' )
                    $out[$section][$m[1]][] = $m[4];
                else
                    $out[$section][$m[1]][$m[3]] = $m[4];
            }
            else
                $out[$section][$m[1]] = $m[4];
        }
        return $out;
    }

    protected function settings()
    {
        $menu = $this->ini( 'menu.ini.append.php' );
        foreach ( $menu as $section => $values )
        {
            if ( strpos( $section, 'Topmenu_' ) === 0 && isset( $values['TranslationContext'] ) )
                foreach ( array( 'Name', 'Tooltip' ) as $key )
                    if ( isset( $values[$key] ) )
                        $this->add( $values['TranslationContext'], $values[$key], "menu.ini [$section] $key" );
            // a left menu (parts/ini_menu.tpl of the kernel): its link, group and description names are translated in
            // the context design/admin/parts/<the part after Leftmenu_>/menu
            if ( strpos( $section, 'Leftmenu_' ) === 0 )
            {
                $context = 'design/admin/parts/' . explode( '_', $section )[1] . '/menu';
                foreach ( array( 'LinkNames', 'GroupNames', 'LinkDescriptions' ) as $key )
                    if ( isset( $values[$key] ) )
                        foreach ( (array)$values[$key] as $name )
                            if ( is_string( $name ) && $name !== '' )
                                $this->add( $context, $name, "menu.ini [$section] $key" );
            }
        }
        if ( isset( $menu['NavigationPart']['Part'] ) )
            foreach ( (array)$menu['NavigationPart']['Part'] as $id => $name )
                $this->add( 'kernel/navigationpart', $name, "menu.ini [NavigationPart] Part[$id]" );
        foreach ( $this->ini( 'kernelservices.ini.append.php' ) as $section => $values )
        {
            if ( strpos( $section, 'Service_transfer.' ) !== 0 )
                continue;
            foreach ( array( 'Name', 'Description' ) as $key )
                if ( isset( $values[$key] ) )
                    $this->add( 'kernel/transfer', $values[$key], "kernelservices.ini [$section] $key" );
            if ( isset( $values['Actions'] ) )
                foreach ( (array)$values['Actions'] as $action )
                {
                    $parts = explode( '|', $action );
                    if ( isset( $parts[2] ) )
                        $this->add( 'kernel/transfer', $parts[2], "kernelservices.ini [$section] Actions" );
                }
        }
    }

    /** @return expImportSourceEntry[] id => entry of every format of the extension's kernelservices.ini */
    public function entries()
    {
        $text = (string)file_get_contents( $this->root . '/settings/kernelservices.ini.append.php' );
        $out = array();
        foreach ( preg_split( '/^\[/m', $text ) as $block )
        {
            if ( strpos( $block, 'Tags[]=sqliimport.source' ) === false )
                continue;
            preg_match_all( '/^Arguments\[\]=(.*)$/m', $block, $m );
            $entry = new expImportSourceEntry( trim( $m[1][0] ), isset( $m[1][1] ) ? trim( $m[1][1] ) : '' );
            $out[$entry->id()] = $entry;
        }
        return $out;
    }

    protected function schema( array $schema, $where, $enumLabels = true )
    {
        foreach ( $schema as $name => $def )
        {
            if ( !is_array( $def ) )
                continue;
            foreach ( array( 'label', 'help' ) as $key )
                if ( isset( $def[$key] ) && is_string( $def[$key] ) )
                    $this->add( 'extension/sqliimport/options', $def[$key], "$where option $name $key" );
            if ( $enumLabels && isset( $def['values'] ) && is_array( $def['values'] ) )
                foreach ( $def['values'] as $value => $label )
                    if ( is_string( $label ) && (string)$label !== (string)$value )
                        $this->add( 'extension/sqliimport/options', $label, "$where option $name value $value" );
        }
    }

    protected function catalogue()
    {
        if ( !class_exists( 'expImportSourceEntry' ) )
            return;
        foreach ( $this->entries() as $id => $entry )
        {
            if ( !$entry->isValid() )
                continue;
            $data = $entry->toArray();
            $class = $data['class'];
            foreach ( array( 'label', 'description', 'notice' ) as $key )
                $this->add( 'extension/sqliimport/catalogue', $data[$key], "format $id $key" );
            // the reason a format is switched off, whatever this site's settings say
            if ( method_exists( $class, 'availability' ) )
                foreach ( self::constantTexts( $class, 'availability' ) as $text )
                    $this->add( 'extension/sqliimport/catalogue', $text, "format $id availability" );
            foreach ( $data['examples'] as $n => $example )
                if ( isset( $example['label'] ) )
                    $this->add( 'extension/sqliimport/catalogue', $example['label'], "format $id example $n" );
            $this->schema( $data['schema'], "format $id" );
        }
        $handlers = array( 'expImportMappingHandler', 'expImportDataTableHandler', 'expImportLegacyHandlerAdapter', 'expImportUndoHandler' );
        foreach ( $handlers as $class )
        {
            if ( !class_exists( $class ) )
                continue;
            $this->add( 'extension/sqliimport/catalogue', $class::label(), "handler $class label" );
            $this->schema( (array)$class::schema(), "handler $class" );
        }
        // the data table handler offers the tables of the kernel as a list: its schema with a table and without
        if ( class_exists( 'expImportDataTableHandler' ) && property_exists( 'expImportDataTableHandler', 'catalogue' ) )
        {
            $previous = expImportDataTableHandler::$catalogue;
            expImportDataTableHandler::$catalogue = function () { return array( 'expSampleRow' => array( 'table' => 'exp_sample_row' ) ); };
            // the labels of its list are the kernel's tables, not texts
            $this->schema( (array)expImportDataTableHandler::schema(), 'handler expImportDataTableHandler (with tables)', false );
            expImportDataTableHandler::$catalogue = $previous;
        }
        if ( class_exists( 'expImportTransforms' ) )
            foreach ( expImportTransforms::builtins() as $name => $def )
            {
                $this->add( 'extension/sqliimport/catalogue', $def[0], "transform $name label" );
                $this->schema( $def[1], "transform $name" );
            }
    }

    /**
     * The literal texts a static method of a class returns (for a text that depends on the settings, such as the
     * reason a format is switched off): the string literals of its body, joined where they are concatenated.
     *
     * @return string[]
     */
    protected static function constantTexts( $class, $method )
    {
        $ref = new ReflectionMethod( $class, $method );
        $lines = array_slice( file( (string)$ref->getFileName() ), $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1 );
        $tokens = token_get_all( '<?php ' . implode( '', $lines ) );
        $out = array();
        $current = null;
        foreach ( $tokens as $t )
        {
            if ( is_array( $t ) && $t[0] === T_RETURN )
                $current = '';
            elseif ( $current !== null && ( $text = self::literal( $t ) ) !== null )
                $current .= $text;
            elseif ( $current !== null && $t === ';' )
            {
                if ( $current !== '' )
                    $out[] = $current;
                $current = null;
            }
        }
        return $out;
    }

    /**
     * The messages of a translation file.
     *
     * @param string $file
     * @return array|null context => source => array( 'translation' => string, 'type' => string ), null when it does not parse
     */
    public static function load( $file )
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors( true );
        $ok = $dom->load( $file );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        if ( !$ok )
            return null;
        $out = array();
        foreach ( ( new DOMXPath( $dom ) )->query( '//context' ) as $context )
        {
            $name = $context->getElementsByTagName( 'name' )->item( 0 )->textContent;
            foreach ( $context->getElementsByTagName( 'message' ) as $message )
            {
                $source = $message->getElementsByTagName( 'source' )->item( 0 );
                $translation = $message->getElementsByTagName( 'translation' )->item( 0 );
                if ( !$source )
                    continue;
                $out[$name][$source->textContent] = array(
                    'translation' => $translation ? $translation->textContent : '',
                    'type' => $translation && $translation->hasAttribute( 'type' ) ? $translation->getAttribute( 'type' ) : '',
                );
            }
        }
        return $out;
    }

    /** @return string[] the %placeholders of a text, sorted (a % followed by a space or the end is a percent sign) */
    public static function placeholders( $text )
    {
        preg_match_all( '/%[A-Za-z_][A-Za-z0-9_]*/', (string)$text, $m );
        $out = $m[0];
        sort( $out );
        return $out;
    }

    /**
     * What a translation file lacks and has on top of the texts of the code.
     *
     * @param array $texts collect()
     * @param array $messages load()
     * @param bool $translated true: every message needs a finished translation with the placeholders of its source
     * @return array ( missing: list of "context | source", extra: list of "context | source", wrong: list of "context | source: why" )
     */
    public static function compare( array $texts, array $messages, $translated )
    {
        $missing = $extra = $wrong = array();
        foreach ( $texts as $context => $sources )
            foreach ( $sources as $source => $where )
            {
                if ( !isset( $messages[$context][$source] ) )
                {
                    $missing[] = "$context | $source | $where";
                    continue;
                }
                $m = $messages[$context][$source];
                if ( !$translated )
                    continue;
                if ( $m['type'] === 'unfinished' || $m['type'] === 'obsolete' || trim( $m['translation'] ) === '' )
                    $wrong[] = "$context | $source: not translated";
                elseif ( self::placeholders( $source ) !== self::placeholders( $m['translation'] ) )
                    $wrong[] = "$context | $source: placeholders " . implode( ' ', self::placeholders( $source ) ) . ' vs ' . implode( ' ', self::placeholders( $m['translation'] ) );
            }
        foreach ( $messages as $context => $sources )
            foreach ( $sources as $source => $m )
                if ( !isset( $texts[$context][$source] ) && !in_array( $context, self::KERNEL_CONTEXTS, true ) )
                    $extra[] = "$context | $source";
        return array( $missing, $extra, $wrong );
    }

    /** the texts translated with a variable: what the variable can be */
    protected function dynamic()
    {
        // ezpI18n::tr( 'extension/sqliimport/type', status_string ) of a 1.x import request (SQLIImportItem)
        foreach ( array( 'Pending', 'Running', 'Failed', 'Completed', 'Interrupted', 'Canceled' ) as $text )
            $this->add( 'extension/sqliimport/type', $text, 'SQLIImportItem::getStatusString()' );
        // expImportStatistics::compare(): self::tr( $def[0] ) of the metrics
        foreach ( array( 'Runs', 'Records processed', 'Failed records', 'Failure rate of records', 'Successful runs', 'Average duration',
                         'Duration, 95th percentile', 'Records per second' ) as $text )
            $this->add( 'extension/sqliimport/statistics', $text, 'expImportStatistics::compare() metric' );
    }
}
