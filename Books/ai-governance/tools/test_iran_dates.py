#!/usr/bin/env python3
from __future__ import annotations

import unittest

from iran_dates import SolarHijriDate, gregorian_to_solar_hijri, localize_persian_dates


class IranDateLocalizationTests(unittest.TestCase):
    def test_nowruz_boundary(self) -> None:
        self.assertEqual(gregorian_to_solar_hijri(2023, 3, 20), SolarHijriDate(1401, 12, 29))
        self.assertEqual(gregorian_to_solar_hijri(2023, 3, 21), SolarHijriDate(1402, 1, 1))

    def test_exact_date(self) -> None:
        self.assertEqual(
            localize_persian_dates("۲۹ مارس ۲۰۲۳"),
            "۲۹ مارس ۲۰۲۳ (۹ فروردین ۱۴۰۲)",
        )

    def test_month_span(self) -> None:
        self.assertEqual(
            localize_persian_dates("مارس ۲۰۲۳"),
            "مارس ۲۰۲۳ (اسفند ۱۴۰۱ تا فروردین ۱۴۰۲)",
        )

    def test_english_bibliography_date_is_normalized(self) -> None:
        self.assertEqual(
            localize_persian_dates("14 July 2021"),
            "۱۴ ژوئیه ۲۰۲۱ (۲۳ تیر ۱۴۰۰)",
        )
        self.assertEqual(
            localize_persian_dates("January 27, 2025"),
            "۲۷ ژانویه ۲۰۲۵ (۸ بهمن ۱۴۰۳)",
        )
        self.assertEqual(
            localize_persian_dates("January 27, 2025 (۲۷ ژانویه ۲۰۲۵)"),
            "۲۷ ژانویه ۲۰۲۵ (۸ بهمن ۱۴۰۳)",
        )

    def test_iso_date_keeps_canonical_notation(self) -> None:
        self.assertEqual(
            localize_persian_dates("2023-03-29"),
            "2023-03-29 (۹ فروردین ۱۴۰۲)",
        )

    def test_urls_and_markdown_list_numbers_are_not_dates(self) -> None:
        text = "https://example.test/2024/08/report\n1\n\nژانویه ۲۰۲۳"
        self.assertEqual(
            localize_persian_dates(text),
            "https://example.test/2024/08/report\n1\n\nژانویه ۲۰۲۳ (دی ۱۴۰۱ تا بهمن ۱۴۰۱)",
        )

    def test_idempotent(self) -> None:
        once = localize_persian_dates("مارس ۲۰۲۳ و ۲۹ مارس ۲۰۲۳ و 2023-03-29")
        self.assertEqual(localize_persian_dates(once), once)


if __name__ == "__main__":
    unittest.main()
