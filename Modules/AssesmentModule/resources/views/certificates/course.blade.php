<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Course Certificate</title>
    @php
        $studentName = $certificate->student?->name ?? 'Student';
        $courseTitle = data_get($certificate->course?->title, 'en', $certificate->course?->slug ?? 'Course');
        $issuedAt = optional($certificate->issued_at)->format('F d, Y');
        $issuedAtTime = optional($certificate->issued_at)->format('H:i');
        $logoPath = public_path('images/certificates/afaaq-logo.jpg');
        $logoData = '';

        if (is_file($logoPath)) {
            $logoType = pathinfo($logoPath, PATHINFO_EXTENSION);
            $logoData = 'data:image/'.$logoType.';base64,'.base64_encode((string) file_get_contents($logoPath));
        }
    @endphp
    <style>
        @page {
            margin: 0;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 0;
            padding: 0;
            color: #172033;
            background: #f5efe2;
        }

        .page {
            width: 100%;
            height: 100%;
            padding: 26px;
            box-sizing: border-box;
            background: #f5efe2;
        }

        .frame {
            position: relative;
            width: 100%;
            height: 100%;
            padding: 28px;
            box-sizing: border-box;
            border: 2px solid #d9c39a;
            background: #fffdf8;
        }

        .frame-inner {
            position: relative;
            width: 100%;
            height: 100%;
            padding: 34px 44px 28px;
            box-sizing: border-box;
            border: 10px solid #1d2740;
            overflow: hidden;
        }

        .ring-top,
        .ring-bottom {
            position: absolute;
            width: 200px;
            height: 200px;
            border: 20px solid #efe2c3;
            border-radius: 50%;
        }

        .ring-top {
            top: -120px;
            right: -90px;
        }

        .ring-bottom {
            bottom: -120px;
            left: -125px;
        }

        .accent-bar {
            width: 110px;
            height: 6px;
            margin: 0 auto 18px;
            background: #c9a45c;
        }

        .logo-wrap {
            text-align: center;
            margin-bottom: 10px;
        }

        .logo {
            width: 110px;
            height: auto;
        }

        .brand {
            text-align: center;
            font-size: 12px;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: #8e6d35;
            margin-bottom: 14px;
        }

        .title {
            text-align: center;
            font-size: 34px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #16203a;
            margin-bottom: 8px;
        }

        .title-sub {
            text-align: center;
            font-size: 13px;
            letter-spacing: 5px;
            text-transform: uppercase;
            color: #90703b;
            margin-bottom: 22px;
        }

        .intro {
            text-align: center;
            font-size: 18px;
            color: #485267;
            margin-bottom: 14px;
        }

        .student {
            text-align: center;
            font-size: 40px;
            font-weight: bold;
            color: #10182d;
            margin-bottom: 14px;
        }

        .student-rule,
        .course-rule {
            width: 420px;
            height: 1px;
            margin: 0 auto;
            background: #d7c29b;
        }

        .student-rule {
            margin-bottom: 18px;
        }

        .body-copy {
            width: 78%;
            margin: 0 auto 18px;
            text-align: center;
            font-size: 17px;
            line-height: 1.75;
            color: #3d465c;
        }

        .course {
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            color: #7c5b20;
            margin-bottom: 12px;
        }

        .course-rule {
            margin-bottom: 20px;
        }

        .achievement {
            width: 72%;
            margin: 0 auto 26px;
            text-align: center;
            font-size: 15px;
            line-height: 1.8;
            color: #50586a;
        }

        .stats {
            width: 78%;
            margin: 0 auto 26px;
            border-collapse: collapse;
        }

        .stats td {
            width: 33.33%;
            padding: 14px 10px;
            text-align: center;
            border: 1px solid #eadcbc;
            background: #fcf7ee;
        }

        .stat-label {
            display: block;
            font-size: 11px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #90703b;
            margin-bottom: 8px;
        }

        .stat-value {
            display: block;
            font-size: 18px;
            font-weight: bold;
            color: #1d2740;
        }

        .footer {
            width: 100%;
            margin-top: 18px;
        }

        .footer td {
            width: 33.33%;
            vertical-align: bottom;
            text-align: center;
        }

        .seal {
            width: 118px;
            height: 118px;
            margin: 0 auto;
            border: 2px solid #d4b57a;
            border-radius: 50%;
            padding-top: 22px;
            box-sizing: border-box;
            color: #7d5f2a;
            background: #fff7e7;
        }

        .seal-title {
            font-size: 13px;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .seal-id {
            font-size: 18px;
            font-weight: bold;
            color: #1d2740;
        }

        .signature-name {
            font-size: 26px;
            font-style: italic;
            color: #1b2440;
            margin-bottom: 6px;
        }

        .signature-line {
            width: 180px;
            height: 1px;
            margin: 0 auto 8px;
            background: #24304f;
        }

        .signature-role {
            font-size: 12px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #8d6a31;
        }

        .student-sign {
            font-size: 22px;
            font-weight: bold;
            color: #172033;
            margin-bottom: 6px;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="frame">
            <div class="frame-inner">
                <div class="ring-top"></div>
                <div class="ring-bottom"></div>

                <div class="accent-bar"></div>

                @if ($logoData !== '')
                    <div class="logo-wrap">
                        <img class="logo" src="{{ $logoData }}" alt="AFAAQ logo">
                    </div>
                @endif

                <div class="brand">Afaaq Educational Platform</div>
                <div class="title">Certificate of Completion</div>
                <div class="title-sub">Official Recognition of Achievement</div>

                <div class="intro">This certificate is proudly presented to</div>
                <div class="student">{{ $studentName }}</div>
                <div class="student-rule"></div>

                <div class="body-copy">
                    In recognition of dedication, consistency, and successful completion of the certified learning experience offered by AFAAQ.
                </div>

                <div class="course">{{ $courseTitle }}</div>
                <div class="course-rule"></div>

                <div class="achievement">
                    The learner has fulfilled the course requirements and demonstrated the expected level of performance for certification.
                </div>

                <table class="stats" cellspacing="0" cellpadding="0">
                    <tr>
                        <td>
                            <span class="stat-label">Weighted Score</span>
                            <span class="stat-value">{{ number_format((float) $certificate->weighted_percentage, 2) }}%</span>
                        </td>
                        <td>
                            <span class="stat-label">Issue Date</span>
                            <span class="stat-value">{{ $issuedAt ?: 'Pending' }}</span>
                        </td>
                        <td>
                            <span class="stat-label">Issue Time</span>
                            <span class="stat-value">{{ $issuedAtTime ?: '--:--' }}</span>
                        </td>
                    </tr>
                </table>

                <table class="footer" cellspacing="0" cellpadding="0">
                    <tr>
                        <td>
                            <div class="student-sign">{{ $studentName }}</div>
                            <div class="signature-line"></div>
                            <div class="signature-role">Certified Learner</div>
                        </td>
                        <td>
                            <div class="seal">
                                <div class="seal-title">Certificate</div>
                                <div class="seal-id">#{{ $certificate->id }}</div>
                            </div>
                        </td>
                        <td>
                            <div class="signature-name">Yara Hamza</div>
                            <div class="signature-line"></div>
                            <div class="signature-role">CEO, AFAAQ</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
