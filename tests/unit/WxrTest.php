<?php

use PHPUnit\Framework\TestCase;

/**
 * The WordPress export (WXR) source: posts with their author, categories, tags and featured image, the media, the
 * terms and the authors, and paragraphs made from blank lines.
 */
class WxrTest extends TestCase
{
    private static function read( array $config ): array
    {
        $out = array();
        $source = new expImportWxrSource( $config + array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/wordpress.xml', 'key' => 'id' ) );
        foreach ( $source->records() as $r )
            $out[] = $r;
        return $out;
    }

    public function testPublishedPostsAndPages(): void
    {
        $posts = self::read( array( 'records' => 'posts', 'post_types' => array( 'post', 'page' ), 'status' => 'publish' ) );
        $this->assertSame( array( '11', '12', '13' ), array_map( function ( $r ) { return $r->ref; }, $posts ), 'the draft and the attachment are left out' );
        $tea = $posts[0]->data;
        $this->assertSame( 'New tea selection', $tea['title'] );
        $this->assertSame( 'post', $tea['type'] );
        $this->assertSame( '2026-04-02T12:30:00Z', $tea['date'] );
        $this->assertSame( 'The Editor', $tea['author_name'] );
        $this->assertSame( array( 'Shop' ), $tea['categories'] );
        $this->assertSame( array( 'tea' ), $tea['tag_slugs'] );
        $this->assertSame( 'https://blog.example.org/wp-content/uploads/2026/03/cup.jpg', $tea['featured_image'] );
        $this->assertSame( array( 'subtitle' => 'Spring 2026' ), $tea['meta'], 'meta without the keys of WordPress itself' );
        $this->assertSame( "<p>Twelve new teas are in.</p>\n<p>Come and taste them<br />\non Saturday.</p>\n<ul>\n<li>Sencha</li>\n<li>Darjeeling</li>\n</ul>", $tea['content_html'] );
        $this->assertSame( '<p>From April the shop opens at <strong>8 am</strong>.</p>', $posts[1]->data['content_html'], 'block editor content keeps its paragraphs' );
        $this->assertSame( 'page', $posts[2]->data['type'] );
        $this->assertCount( 4, self::read( array( 'records' => 'posts', 'post_types' => array( 'post', 'page' ), 'status' => 'all' ) ) );
        $this->assertCount( 1, self::read( array( 'records' => 'posts', 'post_types' => array( 'page' ), 'status' => 'publish' ) ) );
    }

    public function testMediaTermsAndAuthors(): void
    {
        $media = self::read( array( 'records' => 'attachments' ) );
        $this->assertCount( 1, $media );
        $this->assertSame( 'image/jpeg', $media[0]->data['mime'] );
        $this->assertSame( 'A white cup of green tea', $media[0]->data['alt'] );
        $this->assertSame( '11', $media[0]->data['parent_id'] );
        $categories = self::read( array( 'records' => 'categories' ) );
        $this->assertSame( array( 'news', 'shop' ), array_map( function ( $r ) { return $r->ref; }, $categories ) );
        $this->assertSame( 'news', $categories[1]->data['parent'] );
        $this->assertSame( 'Opening hours', self::read( array( 'records' => 'tags' ) )[1]->data['name'] );
        $this->assertSame( 'editor', self::read( array( 'records' => 'authors' ) )[0]->data['login'] );
    }

    public function testResumeAfterACursor(): void
    {
        $posts = self::read( array( 'records' => 'posts', 'post_types' => array( 'post', 'page' ), 'status' => 'publish' ) );
        $rest = array();
        $source = new expImportWxrSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/wordpress.xml', 'key' => 'id', 'records' => 'posts',
                                                 'post_types' => array( 'post', 'page' ), 'status' => 'publish' ) );
        foreach ( $source->records( $posts[0]->cursor ) as $r )
            $rest[] = $r->ref;
        $this->assertSame( array( '12', '13' ), $rest );
    }
}
