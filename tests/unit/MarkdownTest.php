<?php

use PHPUnit\Framework\TestCase;

/**
 * Markdown files with front matter: the YAML and TOML of front matter, Markdown to HTML, and the records of a ZIP of a
 * Jekyll, Hugo and Obsidian style folder.
 */
class MarkdownTest extends TestCase
{
    private static function read( array $config = array() ): array
    {
        $out = array();
        $source = new expImportMarkdownSource( $config + array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/markdown-site.zip', 'key' => 'id' ) );
        foreach ( $source->records() as $r )
            $out[$r->ref] = $r;
        return $out;
    }

    public function testFrontMatter(): void
    {
        list( $front, $body, $format ) = expImportFrontMatter::split( "---\ntitle: \"A: B\"\ntags: [a, 'b c']\ncount: 3\nok: true\nlist:\n  - first\n  - second\nmap:\n  k: v\ntext: |\n  one\n  two\n---\nBody" );
        $this->assertSame( 'yaml', $format );
        $this->assertSame( 'Body', $body );
        $this->assertSame( 'A: B', $front['title'] );
        $this->assertSame( array( 'a', 'b c' ), $front['tags'] );
        $this->assertSame( 3, $front['count'] );
        $this->assertTrue( $front['ok'] );
        $this->assertSame( array( 'first', 'second' ), $front['list'] );
        $this->assertSame( array( 'k' => 'v' ), $front['map'] );
        $this->assertSame( "one\ntwo\n", $front['text'], 'a literal block keeps one final line break' );
        list( $front, , $format ) = expImportFrontMatter::split( "+++\ntitle = \"T\"\ntags = [\"a\", \"b\"]\n[params]\norigin = 'X'\n+++\n" );
        $this->assertSame( 'toml', $format );
        $this->assertSame( array( 'title' => 'T', 'tags' => array( 'a', 'b' ), 'params' => array( 'origin' => 'X' ) ), $front );
        $this->assertSame( array( array(), "No front matter\n", '' ), expImportFrontMatter::split( "No front matter\n" ) );
    }

    public function testMarkdownToHtml(): void
    {
        $this->assertSame( '<h2>Title</h2>' . "\n" . '<p>A <strong>b</strong> <em>c</em> <code>d*e</code> <a href="https://x.org" title="t">l</a></p>',
                           expImportMarkdown::toHtml( "## Title\n\nA **b** *c* `d*e` [l](https://x.org \"t\")" ) );
        $this->assertSame( '<ul><li>one</li><li>two<ul><li>nested</li></ul></li></ul>', expImportMarkdown::toHtml( "- one\n- two\n  - nested" ) );
        $this->assertSame( '<ol><li>a</li><li>b</li></ol>', expImportMarkdown::toHtml( "1. a\n2. b" ) );
        $this->assertSame( '<blockquote><p>q</p></blockquote>', expImportMarkdown::toHtml( '> q' ) );
        $this->assertSame( '<pre><code class="language-php">&lt;?php echo 1;</code></pre>', expImportMarkdown::toHtml( "```php\n<?php echo 1;\n```" ) );
        $this->assertSame( '<p>x</p>', expImportMarkdown::toHtml( '[x](javascript:alert(1))' ), 'no javascript: links' );
        $this->assertSame( '<p>&lt;b&gt; &amp; 1 * 2</p>', expImportMarkdown::toHtml( '\<b\> & 1 \* 2' ) );
        $this->assertSame( '<p>See black and Sencha</p>', expImportMarkdown::toHtml( 'See [[Black tea|black]] and [[Sencha]]' ), 'text only without a base' );
        $this->assertSame( '<p>See <a href="https://wiki.example.org/black-tea">black</a></p>', expImportMarkdown::toHtml( 'See [[Black tea|black]]', 'https://wiki.example.org' ) );
        $this->assertSame( '<table><thead><tr><th>a</th><th>b</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr></tbody></table>',
                           expImportMarkdown::toHtml( "| a | b |\n|---|---|\n| 1 | 2 |" ) );
    }

    public function testRecordsOfTheZip(): void
    {
        $records = self::read();
        $this->assertSame( array( '_posts/2026-03-20-spring-opening-hours', 'content/teas/sencha', 'notes/Tea brewing' ), array_keys( $records ),
                           'layouts, .obsidian and drafts are left out; the folder of the ZIP is no part of the paths' );
        $post = $records['_posts/2026-03-20-spring-opening-hours']->data;
        $this->assertSame( 'Spring opening hours', $post['title'] );
        $this->assertSame( '2026-03-20', $post['date'], 'the date of a Jekyll file name' );
        $this->assertSame( 'spring-opening-hours', $post['slug'] );
        $this->assertSame( array( 'news', 'opening hours' ), $post['tags'] );
        $this->assertSame( array( 'shop' ), $post['categories'] );
        $this->assertSame( 'The Editor', $post['author'], 'front matter keys are fields of their own' );
        $this->assertSame( 'From April the shop opens at 8 am.', $post['summary'] );
        $tea = $records['content/teas/sencha']->data;
        $this->assertSame( 'A Japanese green tea.', $tea['summary'] );
        $this->assertSame( array( 'origin' => 'Shizuoka' ), $tea['params'] );
        $this->assertStringContainsString( '<table>', $tea['html'] );
        $note = $records['notes/Tea brewing']->data;
        $this->assertSame( 'Tea brewing', $note['title'], 'the first heading' );
        $this->assertSame( array( 'Sencha', 'Black tea' ), $note['links'] );
        $this->assertSame( array( 'teapot.png' ), $note['images'] );
        $this->assertSame( '2026-04-10', $note['date'] );
        $this->assertCount( 4, self::read( array( 'drafts' => true ) ) );
        $this->assertCount( 1, self::read( array( 'folder' => 'notes' ) ) );
        $one = array();
        foreach ( ( new expImportMarkdownSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/post.md', 'key' => 'id' ) ) )->records() as $r )
            $one[] = $r;
        $this->assertCount( 1, $one );
        $this->assertSame( 'post', $one[0]->ref );
    }

    public function testResume(): void
    {
        $records = array_values( self::read() );
        $rest = array();
        foreach ( ( new expImportMarkdownSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/markdown-site.zip', 'key' => 'id' ) ) )->records( $records[0]->cursor ) as $r )
            $rest[] = $r->ref;
        $this->assertSame( array( 'content/teas/sencha', 'notes/Tea brewing' ), $rest );
    }
}
