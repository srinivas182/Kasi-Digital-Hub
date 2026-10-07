import { render, screen } from '@testing-library/react';

import { ScoreRing, scoreBand } from '../ScoreRing';

describe('ScoreRing', () => {
    it.each([
        [9.4, 'excellent'],
        [9, 'excellent'],
        [7.6, 'strong'],
        [5, 'partial'],
        [4.9, 'low'],
    ])('score %s is %s', (score, band) => {
        expect(scoreBand(score)).toBe(band);
    });

    it('describes the score for screen readers', () => {
        render(<ScoreRing score={9.4} showCaption />);
        expect(screen.getByRole('img', { name: 'Match score 9.4 out of 10, excellent match' })).toBeInTheDocument();
        expect(screen.getByText('Excellent match')).toBeInTheDocument();
    });

    it('clamps values outside 0-10', () => {
        render(<ScoreRing score={12} />);
        expect(screen.getByRole('img', { name: /10\.0 out of 10/ })).toBeInTheDocument();
    });
});
