# Reporting

## Configure your own timeframes

Place this or a similar config in your `<ICINGAWEB_CONFIGDIR>/modules/reporting/timeframes.ini`:
```ini
[4_hours]
title = "4 Stunden"
start = "-4hours"
end   = "now"

[25_hours]
title = "25 Stunden"
start = "-25hours"
end   = "now"

[one_week]
title = "Eine Woche"
start = "-1week"
end   = "now"

[one_month]
title = "Ein Monat"
start = "-1month"
end   = "now"

[one_year]
title = "Ein Jahr"
start = "-1year"
end   = "now"

[current_week]
title = "KW {START_WEEK} (bis jetzt)"
start = "last monday 00:00:00"
end   = "now"

[last_week]
title = "KW{START_WEEK}/{START_YEAR}"
start = "last monday -1week 00:00:00"
end   = "last sunday 23:59:59"

[minus2_week]
title = "KW{START_WEEK}/{START_YEAR}"
start = "last monday -2week 00:00:00"
end   = "last sunday -1week 23:59:59"

[minus3_week]
title = "KW{START_WEEK}/{START_YEAR}"
start = "last monday -3week 00:00:00"
end   = "last sunday -2week 23:59:59"

[minus4_week]
title = "KW{START_WEEK}/{START_YEAR}"
start = "last monday -4week 00:00:00"
end   = "last sunday -3week 23:59:59"

[current_month]
title = "{START_MONTHNAME} (bis jetzt)"
start = "first day of this month 00:00:00"
end   = "now"

[last_month]
title = "{START_MONTHNAME}"
start = "first day of last month 00:00:00"
end   = "last day of last month 23:59:59"

[minus2_month]
title = "{START_MONTHNAME}"
start = "first day of last month -1month 00:00:00"
end   = "last day of last month -1month 23:59:59"

[minus3_month]
title = "{START_MONTHNAME}"
start = "first day of last month -2month 00:00:00"
end   = "last day of last month -2month 23:59:59"

[minus4_month]
title = "{START_MONTHNAME}"
start = "first day of last month -3month 00:00:00"
end   = "last day of last month -3month 23:59:59"

[minus5_month]
title = "{START_MONTHNAME}"
start = "first day of last month -4month 00:00:00"
end   = "last day of last month -4month 23:59:59"

[current_year]
title = "{START_YEAR} (Dieses Jahr)"
start = "first day of January this year 00:00:00"
end   = "now"

[last_year]
title = "{START_YEAR} (Letztes Jahr)"
start = "first day of January last year 00:00:00"
end   = "last day of December last year 23:59:59"

[minus2_year]
title = "{START_YEAR}"
start = "first day of January last year -1year 00:00:00"
end   = "last day of December last year -1year 23:59:59"

[minus3_year]
title = "{START_YEAR}"
start = "first day of January last year -2year 00:00:00"
end   = "last day of December last year -2year 23:59:59"
```

