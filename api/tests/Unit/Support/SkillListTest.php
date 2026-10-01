<?php

declare(strict_types=1);

use App\Support\ValueObjects\SkillList;

it('lowercases and trims each skill', function () {
    expect(SkillList::normalize(['  React ', 'TypeScript']))->toBe(['react', 'typescript']);
});

it('collapses internal whitespace', function () {
    expect(SkillList::normalize(['machine   learning']))->toBe(['machine learning']);
});

it('removes duplicates that differ only by casing or spacing', function () {
    expect(SkillList::normalize(['React', 'react', ' REACT ']))->toBe(['react']);
});

it('drops empty and whitespace-only entries', function () {
    expect(SkillList::normalize(['react', '', '   ', 'vue']))->toBe(['react', 'vue']);
});

it('skips non-string entries rather than coercing them', function () {
    expect(SkillList::normalize(['react', 42, null, ['nested'], 'vue']))->toBe(['react', 'vue']);
});

it('returns a list with sequential keys so jsonb stores an array, not an object', function () {
    // array_unique preserves keys; without array_values a deduped list would
    // serialize as {"0":"react","2":"vue"} instead of ["react","vue"].
    expect(array_is_list(SkillList::normalize(['react', 'react', 'vue'])))->toBeTrue();
});

it('returns an empty array for an empty input', function () {
    expect(SkillList::normalize([]))->toBe([]);
});

it('does not alias distinct spellings', function () {
    // Collapsing these needs a controlled vocabulary — see the class docblock.
    expect(SkillList::normalize(['React', 'ReactJS']))->toBe(['react', 'reactjs']);
});
