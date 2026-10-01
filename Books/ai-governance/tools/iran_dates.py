#!/usr/bin/env python3
"""Iran-localized Gregorian dates for Persian publication text.

The source date is retained for bibliographic fidelity and a Solar Hijri
equivalent is added. A Gregorian month spans two Solar Hijri months, so
month-only dates are rendered as a range instead of being assigned an
incorrect single Persian month.
"""
from __future__ import annotations

import calendar
import re
from dataclasses import dataclass
from datetime import date


PERSIAN_DIGITS = str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹")
ASCII_DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹", "0123456789")

GREGORIAN_MONTHS_FA: tuple[str, ...] = (
    "", "ژانویه", "فوریه", "مارس", "آوریل", "مه", "ژوئن",
    "ژوئیه", "اوت", "سپتامبر", "اکتبر", "نوامبر", "دسامبر",
)
SOLAR_HIJRI_MONTHS_FA: tuple[str, ...] = (
    "", "فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور",
    "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند",
)

_MONTH_ALIASES: dict[str, int] = {
    "ژانویه": 1, "فوریه": 2, "مارس": 3, "آوریل": 4, "مه": 5,
    "می": 5, "ژوئن": 6, "ژوئیه": 7, "اوت": 8, "آگوست": 8,
    "سپتامبر": 9, "اکتبر": 10, "نوامبر": 11, "دسامبر": 12,
    "january": 1, "february": 2, "march": 3, "april": 4,
    "may": 5, "june": 6, "july": 7, "august": 8,
    "september": 9, "october": 10, "november": 11, "december": 12,
}
_MONTH_PATTERN = "|".join(sorted((re.escape(k) for k in _MONTH_ALIASES), key=len, reverse=True))
_YEAR = r"(?:19|20|۱[۹]|۲[۰])[0-9۰-۹]{2}"
_DAY = r"(?:[12]?[0-9]|3[01]|[۱۲]?[۰-۹]|۳[۰۱])"
_SPACE = r"[ \t\u00a0]+"

# English bibliography style: January 27, 2025.
_EN_MONTH_FIRST = re.compile(
    rf"(?<![\w/])(?P<month>{'|'.join(m.title() for m in _MONTH_ALIASES if m.isascii())})"
    rf"{_SPACE}(?P<day>{_DAY}),?{_SPACE}(?P<year>{_YEAR})(?![\w/])",
    re.IGNORECASE,
)
_BILINGUAL_EN_DATE = re.compile(
    rf"(?<![\w/])(?P<month>{'|'.join(m.title() for m in _MONTH_ALIASES if m.isascii())})"
    rf"{_SPACE}(?P<day>{_DAY}),?{_SPACE}(?P<year>{_YEAR})"
    rf"[ \t\u00a0]*\([ \t\u00a0]*(?P<fa_day>{_DAY}){_SPACE}(?P<fa_month>{_MONTH_PATTERN})"
    rf"{_SPACE}(?P<fa_year>{_YEAR})[ \t\u00a0]*\)",
    re.IGNORECASE,
)
# Persian prose and day-first bibliography style. Whitespace cannot cross a line,
# preventing numbered Markdown items from being mistaken for a day.
_DAY_OR_MONTH_FIRST = re.compile(
    rf"(?<![\w/])(?:(?P<day>{_DAY}){_SPACE})?(?P<month>{_MONTH_PATTERN})"
    rf"{_SPACE}(?P<year>{_YEAR})(?![\w/])",
    re.IGNORECASE,
)
_ISO_DATE = re.compile(
    r"(?<![\w/])(?P<year>(?:19|20)\d{2})-(?P<month>0[1-9]|1[0-2])-(?P<day>0[1-9]|[12]\d|3[01])(?![\w/])"
)
_JALALI_FOLLOWS = re.compile(
    r"^\s*\((?:[۰-۹]{1,2}\s+)?(?:فروردین|اردیبهشت|خرداد|تیر|مرداد|شهریور|مهر|آبان|آذر|دی|بهمن|اسفند)\s+[۰-۹]{4}"
)


@dataclass(frozen=True)
class SolarHijriDate:
    year: int
    month: int
    day: int


def _number(value: str) -> int:
    return int(value.translate(ASCII_DIGITS))


def _fa_number(value: int) -> str:
    return str(value).translate(PERSIAN_DIGITS)


def gregorian_to_solar_hijri(year: int, month: int, day: int) -> SolarHijriDate:
    """Convert a proleptic Gregorian date to the Iranian Solar Hijri calendar."""
    date(year, month, day)  # validate the Gregorian input
    day_offsets = (0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334)
    if year > 1600:
        solar_year = 979
        gregorian_year = year - 1600
    else:
        solar_year = 0
        gregorian_year = year - 621
    adjusted_year = gregorian_year + (1 if month > 2 else 0)
    days = (
        365 * gregorian_year
        + (adjusted_year + 3) // 4
        - (adjusted_year + 99) // 100
        + (adjusted_year + 399) // 400
        - 80
        + day
        + day_offsets[month - 1]
    )
    solar_year += 33 * (days // 12053)
    days %= 12053
    solar_year += 4 * (days // 1461)
    days %= 1461
    if days > 365:
        solar_year += (days - 1) // 365
        days = (days - 1) % 365
    if days < 186:
        solar_month = 1 + days // 31
        solar_day = 1 + days % 31
    else:
        solar_month = 7 + (days - 186) // 30
        solar_day = 1 + (days - 186) % 30
    return SolarHijriDate(solar_year, solar_month, solar_day)


def _solar_exact(year: int, month: int, day: int) -> str:
    solar = gregorian_to_solar_hijri(year, month, day)
    return f"{_fa_number(solar.day)} {SOLAR_HIJRI_MONTHS_FA[solar.month]} {_fa_number(solar.year)}"


def _solar_month_span(year: int, month: int) -> str:
    first = gregorian_to_solar_hijri(year, month, 1)
    last = gregorian_to_solar_hijri(year, month, calendar.monthrange(year, month)[1])
    first_label = f"{SOLAR_HIJRI_MONTHS_FA[first.month]} {_fa_number(first.year)}"
    last_label = f"{SOLAR_HIJRI_MONTHS_FA[last.month]} {_fa_number(last.year)}"
    return first_label if first_label == last_label else f"{first_label} تا {last_label}"


def _already_localized(text: str, end: int) -> bool:
    return bool(_JALALI_FOLLOWS.match(text[end:]))


def _localized_date(month: int, year: int, day: int | None) -> str:
    gregorian = f"{GREGORIAN_MONTHS_FA[month]} {_fa_number(year)}"
    if day is not None:
        gregorian = f"{_fa_number(day)} {gregorian}"
        solar = _solar_exact(year, month, day)
    else:
        solar = _solar_month_span(year, month)
    return f"{gregorian} ({solar})"


def localize_persian_dates(text: str) -> str:
    """Add Iran-calendar equivalents to Gregorian dates in Persian content.

    The transformation is deterministic and idempotent. URLs and slash-delimited
    identifiers are excluded by the date-pattern boundaries.
    """
    def bilingual_english_date(match: re.Match[str]) -> str:
        month = _MONTH_ALIASES[match.group("month").lower()]
        day, year = _number(match.group("day")), _number(match.group("year"))
        translated_month = _MONTH_ALIASES[match.group("fa_month").lower()]
        translated = (_number(match.group("fa_day")), translated_month, _number(match.group("fa_year")))
        if translated != (day, month, year):
            return match.group(0)
        return _localized_date(month, year, day)

    localized = _BILINGUAL_EN_DATE.sub(bilingual_english_date, text)

    def english_month_first(match: re.Match[str]) -> str:
        if _already_localized(localized, match.end()):
            return match.group(0)
        month = _MONTH_ALIASES[match.group("month").lower()]
        return _localized_date(month, _number(match.group("year")), _number(match.group("day")))

    localized = _EN_MONTH_FIRST.sub(english_month_first, localized)

    def day_or_month_first(match: re.Match[str]) -> str:
        if _already_localized(localized, match.end()):
            return match.group(0)
        month = _MONTH_ALIASES[match.group("month").lower()]
        day = _number(match.group("day")) if match.group("day") else None
        return _localized_date(month, _number(match.group("year")), day)

    localized = _DAY_OR_MONTH_FIRST.sub(day_or_month_first, localized)

    def iso_date(match: re.Match[str]) -> str:
        if _already_localized(localized, match.end()):
            return match.group(0)
        year, month, day = (int(match.group(k)) for k in ("year", "month", "day"))
        # Keep ISO notation because it can be a canonical publication-history value.
        return f"{match.group(0)} ({_solar_exact(year, month, day)})"

    return _ISO_DATE.sub(iso_date, localized)
