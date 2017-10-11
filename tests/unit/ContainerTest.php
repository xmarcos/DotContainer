<?php
/**
 * (c) 2017 Marcos Sader.
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace xmarcos\Dot;

use ArrayAccess;
use ArrayObject;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    private $data;

    /**
     * Setup using IssueCommentEvent.json from
     * https://developer.github.com/v3/activity/events/types/#issuecommentevent.
     */
    protected function setUp()
    {
        $this->data = json_decode(
            file_get_contents(__DIR__.'/../fixture/IssueCommentEvent.json'),
            true
        );
    }

    public function testConstructor()
    {
        $dot = new Container();
        $this->assertTrue($dot instanceof Container);
        $this->assertTrue($dot instanceof ArrayAccess);
    }

    public function testCreate()
    {
        $dot = Container::create($this->data);
        $this->assertEquals('xmarcos\Dot\Container', get_class($dot));
        $this->assertCount(count($this->data), $dot);

        unset($dot);
        $dot = Container::create();
        $this->assertCount(0, $dot);

        unset($dot);
        $dot = Container::create(new ArrayObject(['x', 'y', 'z']));
        $this->assertCount(3, $dot);
    }

    public function testLoadData()
    {
        $dot = Container::create($this->data);

        $this->assertEquals(
            count($this->data, COUNT_RECURSIVE),
            count($dot->all(), COUNT_RECURSIVE)
        );

        $serialized = serialize($this->data);
        $this->assertEquals($serialized, serialize($dot->getArrayCopy()));
    }

    public function testAll()
    {
        $dot = Container::create($this->data);

        $this->assertArrayHasKey('issue', $dot->all());
        $this->assertArrayHasKey('comments_url', $dot->all()['issue']);
        $this->assertArrayHasKey('url', $dot->all()['issue']['labels'][0]);
    }

    public function testGet()
    {
        $dot = Container::create($this->data);

        $this->assertArrayHasKey('comments_url', $dot->get('issue'));
        $this->assertArrayHasKey('url', $dot->get('issue.labels.0'));

        $this->assertEquals('fc2929', $dot->get('issue.labels.0.color'));
        $this->assertEquals(6752317, $dot->get('sender.id'));

        $this->assertNull($dot->get('issue.labels.1'));
        $this->assertNull($dot->get('some.missing.key'));

        $dot->set('some.missing.key', md5('key'));
        $this->assertEquals(md5('key'), $dot->get('some.missing.key'));
    }

    public function testHas()
    {
        $dot = Container::create($this->data);

        $this->assertTrue($dot->has('issue'));
        $this->assertTrue($dot->has('issue.labels.0.color'));
        $this->assertFalse($dot->has('issue.labels.1.color'));
        $this->assertFalse($dot->has('issue.labels.1.name'));
    }

    public function testSet()
    {
        $dot = Container::create($this->data);

        $dot->set('key', 'value');
        $dot->set('nested.key', 'nested_value');
        $dot->set('N.E.S.T.E.D', range(0, 5));

        $this->assertArrayHasKey('key', $dot->all());
        $this->assertArrayHasKey('nested', $dot->all());
        $this->assertArrayHasKey('N', $dot->all());

        $this->assertEquals($dot->get('key'), 'value');
        $this->assertEquals($dot->get('nested'), ['key' => 'nested_value']);
        $this->assertEquals($dot->get('N'), [
            'E' => ['S' => ['T' => ['E' => ['D' => range(0, 5)]]]],
        ]);

        //Extend
        $dot->set('N.E.S.T', array_merge(
            $dot->get('N.E.S.T'),
            ['extended' => true]
        ));

        $this->assertArrayHasKey('extended', $dot->get('N.E.S.T'));
        $this->assertArrayHasKey('E', $dot->get('N.E.S.T'));
        $this->assertArrayHasKey('D', $dot->get('N.E.S.T.E'));
    }

    public function testDelete()
    {
        $dot = Container::create($this->data);

        $this->assertTrue($dot->has('issue'));
        $this->assertTrue($dot->has('issue.labels.0.color'));

        $dot->delete('issue.labels.0.color');

        $this->assertTrue($dot->has('issue.labels.0.url'));
        $this->assertFalse($dot->has('issue.labels.0.color'));

        $dot->delete('issue.labels.0');

        $this->assertFalse($dot->has('issue.labels.0.url'));
        $this->assertFalse($dot->has('issue.labels.0'));

        $dot->delete('sender.id');

        $this->assertFalse($dot->has('sender.id'));
        $this->assertTrue($dot->has('sender'));
        $this->assertTrue($dot->has('sender.login'));
    }

    public function testReset()
    {
        $dot = Container::create($this->data);

        $this->assertArrayHasKey('issue', $dot->all());
        $this->assertArrayHasKey('id', $dot->get('sender'));

        $dot->reset();

        $this->assertArrayNotHasKey('issue', $dot->all());
        $this->assertNull($dot->get('sender.id'));
        $this->assertCount(0, $dot->all());
        $this->assertEmpty($dot->all());
    }

    public function testArrayObjectMethods()
    {
        $dot = Container::create($this->data);

        $this->assertCount(count($this->data), $dot->getArrayCopy());

        $this->assertArrayHasKey('issue', $dot);
        $this->assertArrayHasKey('sender', $dot);
        $this->assertArrayHasKey('id', $dot['sender']);
        $this->assertArrayNotHasKey('commit', $dot);

        $dot['x'] = true;
        $this->assertArrayHasKey('x', $dot);
        $this->assertEquals(true, $dot->get('x'));

        $dot->set('z', 'Zeta');
        $this->assertArrayHasKey('z', $dot);
        $this->assertEquals('Zeta', $dot['z']);

        $this->assertTrue(isset($dot['z']));
        $this->assertTrue(isset($dot['issue']['labels'][0]['url']));
        $this->assertFalse(isset($dot['issue']['labels'][1]['url']));
    }

    public function testFluentInterface()
    {
        $this->assertEquals('fc2929', Container::create($this->data)->get('issue.labels.0.color'));
        $this->assertEquals(6752317, Container::create($this->data)->get('sender.id'));

        $dot = Container::create()
            ->set('x', true)
            ->set('y', false)
            ->set('z', 'z');

        $this->assertCount(3, $dot);

        $this->assertTrue($dot->has('z'));
        $this->assertFalse($dot->delete('z')->set('x.0.a', 'x0a')->has('z'));

        $x0a = serialize([['a' => 'x0a']]);
        $this->assertEquals($x0a, serialize($dot->get('x')));
    }
}
