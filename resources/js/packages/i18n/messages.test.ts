import { describe, expect, it } from 'vitest';
import { messages } from './messages';

describe('locale catalogs', () => {
    it('keeps Myanmar and English message keys aligned', () => {
        expect(Object.keys(messages['my-MM']).sort()).toEqual(Object.keys(messages.en).sort());
    });

    it('does not ship empty translations', () => {
        Object.values(messages).forEach((catalog) => {
            Object.values(catalog).forEach((message) => expect(message.trim()).not.toBe(''));
        });
    });
});
