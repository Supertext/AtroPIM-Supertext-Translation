<?php

namespace SupertextTranslation\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SupertextTranslation\Translation\Planner;

class PlannerTest extends TestCase
{
    /** Field definitions as AtroCore builds them for a product with one attribute. */
    private const DEFS = [
        'name'               => ['type' => 'varchar', 'isMultilang' => true, 'maxLength' => 255],
        'nameDeCh'           => ['type' => 'varchar', 'multilangField' => 'name', 'multilangLocale' => 'de_CH'],
        'nameFrCh'           => ['type' => 'varchar', 'multilangField' => 'name', 'multilangLocale' => 'fr_CH'],
        'longDescription'    => ['type' => 'wysiwyg', 'isMultilang' => true],
        'longDescriptionDeCh' => ['type' => 'wysiwyg', 'multilangField' => 'longDescription', 'multilangLocale' => 'de_CH'],
        'tastingNotes'       => ['type' => 'text', 'isMultilang' => true, 'label' => 'Tasting notes'],
        'tastingNotesDeCh'   => ['type' => 'text', 'multilangField' => 'tastingNotes', 'multilangLocale' => 'de_CH'],
        'sku'                => ['type' => 'varchar'],
        'color'              => ['type' => 'enum', 'isMultilang' => true],
        'colorDeCh'          => ['type' => 'enum', 'multilangField' => 'color', 'multilangLocale' => 'de_CH'],
        'locked'             => ['type' => 'varchar', 'isMultilang' => true, 'readOnly' => true],
        'lockedDeCh'         => ['type' => 'varchar', 'multilangField' => 'locked', 'multilangLocale' => 'de_CH'],
        'single'             => ['type' => 'varchar', 'isMultilang' => true],
    ];

    public function testCollectsMultilingualTextFieldsOnly(): void
    {
        $units = Planner::units(self::DEFS, 'en_US');

        self::assertSame(['name', 'longDescription', 'tastingNotes'], array_map(static fn ($u) => $u->field, $units));
        self::assertSame(['en_US' => 'name', 'de_CH' => 'nameDeCh', 'fr_CH' => 'nameFrCh'], $units[0]->fieldsByLanguage);
        self::assertSame(255, $units[0]->maxLength);
        self::assertFalse($units[0]->html);
        self::assertTrue($units[1]->html);
        self::assertSame('Tasting notes', $units[2]->label);
        self::assertSame(['en_US', 'de_CH', 'fr_CH'], Planner::languages($units));
    }

    public function testPlanKeepsExistingTextUnlessOverwrite(): void
    {
        $units  = Planner::units(self::DEFS, 'en_US');
        $values = [
            'name' => 'Praline box', 'nameDeCh' => 'Pralinenschachtel', 'nameFrCh' => '',
            'longDescription' => '<p>Filled by hand.</p>', 'longDescriptionDeCh' => '<p><br></p>',
            'tastingNotes' => '', 'tastingNotesDeCh' => 'Alt',
        ];

        $plan = Planner::plan($units, $values, 'en_US', 'de_CH', false);
        self::assertSame(['longDescription'], array_map(static fn ($u) => $u->field, $plan['translate']));
        self::assertSame(1, $plan['existing']);
        self::assertSame(1, $plan['noSource']);

        $plan = Planner::plan($units, $values, 'en_US', 'de_CH', true);
        self::assertSame(['name', 'longDescription'], array_map(static fn ($u) => $u->field, $plan['translate']));

        // fr_CH only exists for name.
        $plan = Planner::plan($units, $values, 'en_US', 'fr_CH', false);
        self::assertSame(['name'], array_map(static fn ($u) => $u->field, $plan['translate']));
    }

    public function testTranslatesFromAnAdditionalLanguage(): void
    {
        $units = Planner::units(self::DEFS, 'en_US');
        $plan  = Planner::plan($units, ['name' => '', 'nameDeCh' => 'Pralinen', 'nameFrCh' => ''], 'de_CH', 'en_US', false);

        self::assertSame(['name'], array_map(static fn ($u) => $u->field, $plan['translate']));
    }

    public function testHasText(): void
    {
        self::assertFalse(Planner::hasText(null));
        self::assertFalse(Planner::hasText('<p>&nbsp;</p>'));
        self::assertFalse(Planner::hasText(" \n "));
        self::assertTrue(Planner::hasText('<p>x</p>'));
    }

    public function testSupertextCode(): void
    {
        self::assertSame('de-CH', Planner::supertextCode('de_CH'));
        self::assertSame('en', Planner::supertextCode('en'));
    }
}
