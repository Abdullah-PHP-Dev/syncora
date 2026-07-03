<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            // A
            ['name' => 'Afghanistan', 'code' => 'AFG', 'dial_code' => '+93', 'currency' => 'AFN'],
            ['name' => 'Albania', 'code' => 'ALB', 'dial_code' => '+355', 'currency' => 'ALL'],
            ['name' => 'Algeria', 'code' => 'DZA', 'dial_code' => '+213', 'currency' => 'DZD'],
            ['name' => 'American Samoa', 'code' => 'ASM', 'dial_code' => '+1684', 'currency' => 'USD'],
            ['name' => 'Andorra', 'code' => 'AND', 'dial_code' => '+376', 'currency' => 'EUR'],
            ['name' => 'Angola', 'code' => 'AGO', 'dial_code' => '+244', 'currency' => 'AOA'],
            ['name' => 'Anguilla', 'code' => 'AIA', 'dial_code' => '+1264', 'currency' => 'XCD'],
            ['name' => 'Antigua and Barbuda', 'code' => 'ATG', 'dial_code' => '+1268', 'currency' => 'XCD'],
            ['name' => 'Argentina', 'code' => 'ARG', 'dial_code' => '+54', 'currency' => 'ARS'],
            ['name' => 'Armenia', 'code' => 'ARM', 'dial_code' => '+374', 'currency' => 'AMD'],
            ['name' => 'Aruba', 'code' => 'ABW', 'dial_code' => '+297', 'currency' => 'AWG'],
            ['name' => 'Australia', 'code' => 'AUS', 'dial_code' => '+61', 'currency' => 'AUD'],
            ['name' => 'Austria', 'code' => 'AUT', 'dial_code' => '+43', 'currency' => 'EUR'],
            ['name' => 'Azerbaijan', 'code' => 'AZE', 'dial_code' => '+994', 'currency' => 'AZN'],

            // B
            ['name' => 'The Bahamas', 'code' => 'BHS', 'dial_code' => '+1242', 'currency' => 'BSD'],
            ['name' => 'Bahrain', 'code' => 'BHR', 'dial_code' => '+973', 'currency' => 'BHD'],
            ['name' => 'Bangladesh', 'code' => 'BGD', 'dial_code' => '+880', 'currency' => 'BDT'],
            ['name' => 'Barbados', 'code' => 'BRB', 'dial_code' => '+1246', 'currency' => 'BBD'],
            ['name' => 'Belarus', 'code' => 'BLR', 'dial_code' => '+375', 'currency' => 'BYN'],
            ['name' => 'Belgium', 'code' => 'BEL', 'dial_code' => '+32', 'currency' => 'EUR'],
            ['name' => 'Belize', 'code' => 'BLZ', 'dial_code' => '+501', 'currency' => 'BZD'],
            ['name' => 'Benin', 'code' => 'BEN', 'dial_code' => '+229', 'currency' => 'XOF'],
            ['name' => 'Bermuda', 'code' => 'BMU', 'dial_code' => '+1441', 'currency' => 'BMD'],
            ['name' => 'Bhutan', 'code' => 'BTN', 'dial_code' => '+975', 'currency' => 'BTN'],
            ['name' => 'Bolivia', 'code' => 'BOL', 'dial_code' => '+591', 'currency' => 'BOB'],
            ['name' => 'Bosnia and Herzegovina', 'code' => 'BIH', 'dial_code' => '+387', 'currency' => 'BAM'],
            ['name' => 'Botswana', 'code' => 'BWA', 'dial_code' => '+267', 'currency' => 'BWP'],
            ['name' => 'Brazil', 'code' => 'BRA', 'dial_code' => '+55', 'currency' => 'BRL'],
            ['name' => 'British Virgin Islands', 'code' => 'VGB', 'dial_code' => '+1284', 'currency' => 'USD'],
            ['name' => 'Brunei', 'code' => 'BRN', 'dial_code' => '+673', 'currency' => 'BND'],
            ['name' => 'Bulgaria', 'code' => 'BGR', 'dial_code' => '+359', 'currency' => 'BGN'],
            ['name' => 'Burkina Faso', 'code' => 'BFA', 'dial_code' => '+226', 'currency' => 'XOF'],
            ['name' => 'Burundi', 'code' => 'BDI', 'dial_code' => '+257', 'currency' => 'BIF'],

            // C
            ['name' => 'Cabo Verde (Cape Verde)', 'code' => 'CPV', 'dial_code' => '+238', 'currency' => 'CVE'],
            ['name' => 'Cambodia', 'code' => 'KHM', 'dial_code' => '+855', 'currency' => 'KHR'],
            ['name' => 'Cameroon', 'code' => 'CMR', 'dial_code' => '+237', 'currency' => 'XAF'],
            ['name' => 'Canada', 'code' => 'CAN', 'dial_code' => '+1', 'currency' => 'CAD'],
            ['name' => 'Cayman Islands', 'code' => 'CYM', 'dial_code' => '+1345', 'currency' => 'KYD'],
            ['name' => 'Central African Republic', 'code' => 'CAF', 'dial_code' => '+236', 'currency' => 'XAF'],
            ['name' => 'Chad', 'code' => 'TCD', 'dial_code' => '+235', 'currency' => 'XAF'],
            ['name' => 'Chile', 'code' => 'CHL', 'dial_code' => '+56', 'currency' => 'CLP'],
            ['name' => 'China', 'code' => 'CHN', 'dial_code' => '+86', 'currency' => 'CNY'],
            ['name' => 'Cocos (Keeling) Islands', 'code' => 'CCK', 'dial_code' => '+61', 'currency' => 'AUD'],
            ['name' => 'Colombia', 'code' => 'COL', 'dial_code' => '+57', 'currency' => 'COP'],
            ['name' => 'Comoros', 'code' => 'COM', 'dial_code' => '+269', 'currency' => 'KMF'],
            ['name' => 'Democratic Republic of the Congo', 'code' => 'COD', 'dial_code' => '+243', 'currency' => 'CDF'],
            ['name' => 'Republic of the Congo', 'code' => 'COG', 'dial_code' => '+242', 'currency' => 'XAF'],
            ['name' => 'Cook Islands', 'code' => 'COK', 'dial_code' => '+682', 'currency' => 'NZD'],
            ['name' => 'Costa Rica', 'code' => 'CRI', 'dial_code' => '+506', 'currency' => 'CRC'],
            ['name' => 'Côte d’Ivoire', 'code' => 'CIV', 'dial_code' => '+225', 'currency' => 'XOF'],
            ['name' => 'Croatia', 'code' => 'HRV', 'dial_code' => '+385', 'currency' => 'EUR'],
            ['name' => 'Cuba', 'code' => 'CUB', 'dial_code' => '+53', 'currency' => 'CUP'],
            ['name' => 'Curaçao', 'code' => 'CUW', 'dial_code' => '+599', 'currency' => 'ANG'],
            ['name' => 'Cyprus', 'code' => 'CYP', 'dial_code' => '+357', 'currency' => 'EUR'],
            ['name' => 'Czech Republic', 'code' => 'CZE', 'dial_code' => '+420', 'currency' => 'CZK'],

            // D
            ['name' => 'Denmark', 'code' => 'DNK', 'dial_code' => '+45', 'currency' => 'DKK'],
            ['name' => 'Djibouti', 'code' => 'DJI', 'dial_code' => '+253', 'currency' => 'DJF'],
            ['name' => 'Dominica', 'code' => 'DMA', 'dial_code' => '+1767', 'currency' => 'XCD'],
            ['name' => 'Dominican Republic', 'code' => 'DOM', 'dial_code' => '+1849', 'currency' => 'DOP'],

            // E
            ['name' => 'East Timor (Timor-Leste)', 'code' => 'TLS', 'dial_code' => '+670', 'currency' => 'USD'],
            ['name' => 'Ecuador', 'code' => 'ECU', 'dial_code' => '+593', 'currency' => 'USD'],
            ['name' => 'Egypt', 'code' => 'EGY', 'dial_code' => '+20', 'currency' => 'EGP'],
            ['name' => 'El Salvador', 'code' => 'SLV', 'dial_code' => '+503', 'currency' => 'USD'],
            ['name' => 'Equatorial Guinea', 'code' => 'GNQ', 'dial_code' => '+240', 'currency' => 'XAF'],
            ['name' => 'Eritrea', 'code' => 'ERI', 'dial_code' => '+291', 'currency' => 'ERN'],
            ['name' => 'Estonia', 'code' => 'EST', 'dial_code' => '+372', 'currency' => 'EUR'],
            ['name' => 'Eswatini (Swaziland)', 'code' => 'SWZ', 'dial_code' => '+268', 'currency' => 'SZL'],
            ['name' => 'Ethiopia', 'code' => 'ETH', 'dial_code' => '+251', 'currency' => 'ETB'],

            // F
            ['name' => 'Falkland Islands', 'code' => 'FLK', 'dial_code' => '+500', 'currency' => 'FKP'],
            ['name' => 'Faroe Islands', 'code' => 'FRO', 'dial_code' => '+298', 'currency' => 'DKK'],
            ['name' => 'Fiji', 'code' => 'FJI', 'dial_code' => '+679', 'currency' => 'FJD'],
            ['name' => 'Finland', 'code' => 'FIN', 'dial_code' => '+358', 'currency' => 'EUR'],
            ['name' => 'France', 'code' => 'FRA', 'dial_code' => '+33', 'currency' => 'EUR'],
            ['name' => 'French Guiana', 'code' => 'GUF', 'dial_code' => '+594', 'currency' => 'EUR'],
            ['name' => 'French Polynesia', 'code' => 'PYF', 'dial_code' => '+689', 'currency' => 'XPF'],

            // G
            ['name' => 'Gabon', 'code' => 'GAB', 'dial_code' => '+241', 'currency' => 'XAF'],
            ['name' => 'The Gambia', 'code' => 'GMB', 'dial_code' => '+220', 'currency' => 'GMD'],
            ['name' => 'Gaza Strip', 'code' => 'PSE', 'dial_code' => '+970', 'currency' => 'ILS'],
            ['name' => 'Georgia', 'code' => 'GEO', 'dial_code' => '+995', 'currency' => 'GEL'],
            ['name' => 'Germany', 'code' => 'DEU', 'dial_code' => '+49', 'currency' => 'EUR'],
            ['name' => 'Ghana', 'code' => 'GHA', 'dial_code' => '+233', 'currency' => 'GHS'],
            ['name' => 'Gibraltar', 'code' => 'GIB', 'dial_code' => '+350', 'currency' => 'GIP'],
            ['name' => 'Greece', 'code' => 'GRC', 'dial_code' => '+30', 'currency' => 'EUR'],
            ['name' => 'Greenland', 'code' => 'GRL', 'dial_code' => '+299', 'currency' => 'DKK'],
            ['name' => 'Grenada', 'code' => 'GRD', 'dial_code' => '+1473', 'currency' => 'XCD'],
            ['name' => 'Guadeloupe', 'code' => 'GLP', 'dial_code' => '+590', 'currency' => 'EUR'],
            ['name' => 'Guam', 'code' => 'GUM', 'dial_code' => '+1671', 'currency' => 'USD'],
            ['name' => 'Guatemala', 'code' => 'GTM', 'dial_code' => '+502', 'currency' => 'GTQ'],
            ['name' => 'Guernsey', 'code' => 'GGY', 'dial_code' => '+44', 'currency' => 'GBP'],
            ['name' => 'Guinea', 'code' => 'GIN', 'dial_code' => '+224', 'currency' => 'GNF'],
            ['name' => 'Guinea-Bissau', 'code' => 'GNB', 'dial_code' => '+245', 'currency' => 'XOF'],
            ['name' => 'Guyana', 'code' => 'GUY', 'dial_code' => '+592', 'currency' => 'GYD'],

            // H
            ['name' => 'Haiti', 'code' => 'HTI', 'dial_code' => '+509', 'currency' => 'HTG'],
            ['name' => 'Honduras', 'code' => 'HND', 'dial_code' => '+504', 'currency' => 'HNL'],
            ['name' => 'Hong Kong', 'code' => 'HKG', 'dial_code' => '+852', 'currency' => 'HKD'],
            ['name' => 'Hungary', 'code' => 'HUN', 'dial_code' => '+36', 'currency' => 'HUF'],

            // I
            ['name' => 'Iceland', 'code' => 'ISL', 'dial_code' => '+354', 'currency' => 'ISK'],
            ['name' => 'India', 'code' => 'IND', 'dial_code' => '+91', 'currency' => 'INR'],
            ['name' => 'Indonesia', 'code' => 'IDN', 'dial_code' => '+62', 'currency' => 'IDR'],
            ['name' => 'Iran', 'code' => 'IRN', 'dial_code' => '+98', 'currency' => 'IRR'],
            ['name' => 'Iraq', 'code' => 'IRQ', 'dial_code' => '+964', 'currency' => 'IQD'],
            ['name' => 'Ireland', 'code' => 'IRL', 'dial_code' => '+353', 'currency' => 'EUR'],
            ['name' => 'Isle of Man', 'code' => 'IMN', 'dial_code' => '+44', 'currency' => 'GBP'],
            ['name' => 'Israel', 'code' => 'ISR', 'dial_code' => '+972', 'currency' => 'ILS'],
            ['name' => 'Italy', 'code' => 'ITA', 'dial_code' => '+39', 'currency' => 'EUR'],

            // J
            ['name' => 'Jamaica', 'code' => 'JAM', 'dial_code' => '+1876', 'currency' => 'JMD'],
            ['name' => 'Japan', 'code' => 'JPN', 'dial_code' => '+81', 'currency' => 'JPY'],
            ['name' => 'Jersey', 'code' => 'JEY', 'dial_code' => '+44', 'currency' => 'GBP'],
            ['name' => 'Jordan', 'code' => 'JOR', 'dial_code' => '+962', 'currency' => 'JOD'],

            // K
            ['name' => 'Kazakhstan', 'code' => 'KAZ', 'dial_code' => '+7', 'currency' => 'KZT'],
            ['name' => 'Kenya', 'code' => 'KEN', 'dial_code' => '+254', 'currency' => 'KES'],
            ['name' => 'Kiribati', 'code' => 'KIR', 'dial_code' => '+686', 'currency' => 'AUD'],
            ['name' => 'North Korea', 'code' => 'PRK', 'dial_code' => '+850', 'currency' => 'KPW'],
            ['name' => 'South Korea', 'code' => 'KOR', 'dial_code' => '+82', 'currency' => 'KRW'],
            ['name' => 'Kosovo', 'code' => 'XKX', 'dial_code' => '+383', 'currency' => 'EUR'],
            ['name' => 'Kuwait', 'code' => 'KWT', 'dial_code' => '+965', 'currency' => 'KWD'],
            ['name' => 'Kyrgyzstan', 'code' => 'KGZ', 'dial_code' => '+996', 'currency' => 'KGS'],

            // L
            ['name' => 'Laos', 'code' => 'LAO', 'dial_code' => '+856', 'currency' => 'LAK'],
            ['name' => 'Latvia', 'code' => 'LVA', 'dial_code' => '+371', 'currency' => 'EUR'],
            ['name' => 'Lebanon', 'code' => 'LBN', 'dial_code' => '+961', 'currency' => 'LBP'],
            ['name' => 'Lesotho', 'code' => 'LSO', 'dial_code' => '+266', 'currency' => 'LSL'],
            ['name' => 'Liberia', 'code' => 'LBR', 'dial_code' => '+231', 'currency' => 'LRD'],
            ['name' => 'Libya', 'code' => 'LBY', 'dial_code' => '+218', 'currency' => 'LYD'],
            ['name' => 'Liechtenstein', 'code' => 'LIE', 'dial_code' => '+423', 'currency' => 'CHF'],
            ['name' => 'Lithuania', 'code' => 'LTU', 'dial_code' => '+370', 'currency' => 'EUR'],
            ['name' => 'Luxembourg', 'code' => 'LUX', 'dial_code' => '+352', 'currency' => 'EUR'],

            // M
            ['name' => 'Macau', 'code' => 'MAC', 'dial_code' => '+853', 'currency' => 'MOP'],
            ['name' => 'Madagascar', 'code' => 'MDG', 'dial_code' => '+261', 'currency' => 'MGA'],
            ['name' => 'Malawi', 'code' => 'MWI', 'dial_code' => '+265', 'currency' => 'MWK'],
            ['name' => 'Malaysia', 'code' => 'MYS', 'dial_code' => '+60', 'currency' => 'MYR'],
            ['name' => 'Maldives', 'code' => 'MDV', 'dial_code' => '+960', 'currency' => 'MVR'],
            ['name' => 'Mali', 'code' => 'MLI', 'dial_code' => '+223', 'currency' => 'XOF'],
            ['name' => 'Malta', 'code' => 'MLT', 'dial_code' => '+356', 'currency' => 'EUR'],
            ['name' => 'Marshall Islands', 'code' => 'MHL', 'dial_code' => '+692', 'currency' => 'USD'],
            ['name' => 'Martinique', 'code' => 'MTQ', 'dial_code' => '+596', 'currency' => 'EUR'],
            ['name' => 'Mauritania', 'code' => 'MRT', 'dial_code' => '+222', 'currency' => 'MRU'],
            ['name' => 'Mauritius', 'code' => 'MUS', 'dial_code' => '+230', 'currency' => 'MUR'],
            ['name' => 'Mayotte', 'code' => 'MYT', 'dial_code' => '+262', 'currency' => 'EUR'],
            ['name' => 'Mexico', 'code' => 'MEX', 'dial_code' => '+52', 'currency' => 'MXN'],
            ['name' => 'Micronesia', 'code' => 'FSM', 'dial_code' => '+691', 'currency' => 'USD'],
            ['name' => 'Moldova', 'code' => 'MDA', 'dial_code' => '+373', 'currency' => 'MDL'],
            ['name' => 'Monaco', 'code' => 'MCO', 'dial_code' => '+377', 'currency' => 'EUR'],
            ['name' => 'Mongolia', 'code' => 'MNG', 'dial_code' => '+976', 'currency' => 'MNT'],
            ['name' => 'Montenegro', 'code' => 'MNE', 'dial_code' => '+382', 'currency' => 'EUR'],
            ['name' => 'Montserrat', 'code' => 'MSR', 'dial_code' => '+1664', 'currency' => 'XCD'],
            ['name' => 'Morocco', 'code' => 'MAR', 'dial_code' => '+212', 'currency' => 'MAD'],
            ['name' => 'Mozambique', 'code' => 'MOZ', 'dial_code' => '+258', 'currency' => 'MZN'],
            ['name' => 'Myanmar (Burma)', 'code' => 'MMR', 'dial_code' => '+95', 'currency' => 'MMK'],

            // N
            ['name' => 'Namibia', 'code' => 'NAM', 'dial_code' => '+264', 'currency' => 'NAD'],
            ['name' => 'Nauru', 'code' => 'NRU', 'dial_code' => '+674', 'currency' => 'AUD'],
            ['name' => 'Nepal', 'code' => 'NPL', 'dial_code' => '+977', 'currency' => 'NPR'],
            ['name' => 'Netherlands', 'code' => 'NLD', 'dial_code' => '+31', 'currency' => 'EUR'],
            ['name' => 'New Caledonia', 'code' => 'NCL', 'dial_code' => '+687', 'currency' => 'XPF'],
            ['name' => 'New Zealand', 'code' => 'NZL', 'dial_code' => '+64', 'currency' => 'NZD'],
            ['name' => 'Nicaragua', 'code' => 'NIC', 'dial_code' => '+505', 'currency' => 'NIO'],
            ['name' => 'Niger', 'code' => 'NER', 'dial_code' => '+227', 'currency' => 'XOF'],
            ['name' => 'Nigeria', 'code' => 'NGA', 'dial_code' => '+234', 'currency' => 'NGN'],
            ['name' => 'Niue', 'code' => 'NIU', 'dial_code' => '+683', 'currency' => 'NZD'],
            ['name' => 'North Macedonia', 'code' => 'MKD', 'dial_code' => '+389', 'currency' => 'MKD'],
            ['name' => 'Northern Mariana Islands', 'code' => 'MNP', 'dial_code' => '+1670', 'currency' => 'USD'],
            ['name' => 'Norway', 'code' => 'NOR', 'dial_code' => '+47', 'currency' => 'NOK'],

            // O
            ['name' => 'Oman', 'code' => 'OMN', 'dial_code' => '+968', 'currency' => 'OMR'],

            // P
            ['name' => 'Pakistan', 'code' => 'PAK', 'dial_code' => '+92', 'currency' => 'PKR'],
            ['name' => 'Palau', 'code' => 'PLW', 'dial_code' => '+680', 'currency' => 'USD'],
            ['name' => 'Panama', 'code' => 'PAN', 'dial_code' => '+507', 'currency' => 'PAB'],
            ['name' => 'Papua New Guinea', 'code' => 'PNG', 'dial_code' => '+675', 'currency' => 'PGK'],
            ['name' => 'Paraguay', 'code' => 'PRY', 'dial_code' => '+595', 'currency' => 'PYG'],
            ['name' => 'Peru', 'code' => 'PER', 'dial_code' => '+51', 'currency' => 'PEN'],
            ['name' => 'Philippines', 'code' => 'PHL', 'dial_code' => '+63', 'currency' => 'PHP'],
            ['name' => 'Pitcairn Island', 'code' => 'PCN', 'dial_code' => '+64', 'currency' => 'NZD'],
            ['name' => 'Poland', 'code' => 'POL', 'dial_code' => '+48', 'currency' => 'PLN'],
            ['name' => 'Portugal', 'code' => 'PRT', 'dial_code' => '+351', 'currency' => 'EUR'],
            ['name' => 'Puerto Rico', 'code' => 'PRI', 'dial_code' => '+1787', 'currency' => 'USD'],

            // Q
            ['name' => 'Qatar', 'code' => 'QAT', 'dial_code' => '+974', 'currency' => 'QAR'],

            // R
            ['name' => 'Réunion', 'code' => 'REU', 'dial_code' => '+262', 'currency' => 'EUR'],
            ['name' => 'Romania', 'code' => 'ROU', 'dial_code' => '+40', 'currency' => 'RON'],
            ['name' => 'Russia', 'code' => 'RUS', 'dial_code' => '+7', 'currency' => 'RUB'],
            ['name' => 'Rwanda', 'code' => 'RWA', 'dial_code' => '+250', 'currency' => 'RWF'],

            // S
            ['name' => 'Saint Helena', 'code' => 'SHN', 'dial_code' => '+290', 'currency' => 'SHP'],
            ['name' => 'Saint Kitts and Nevis', 'code' => 'KNA', 'dial_code' => '+1869', 'currency' => 'XCD'],
            ['name' => 'Saint Lucia', 'code' => 'LCA', 'dial_code' => '+1758', 'currency' => 'XCD'],
            ['name' => 'Saint-Pierre and Miquelon', 'code' => 'SPM', 'dial_code' => '+508', 'currency' => 'EUR'],
            ['name' => 'Saint Vincent and the Grenadines', 'code' => 'VCT', 'dial_code' => '+1784', 'currency' => 'XCD'],
            ['name' => 'Samoa', 'code' => 'WSM', 'dial_code' => '+685', 'currency' => 'WST'],
            ['name' => 'San Marino', 'code' => 'SMR', 'dial_code' => '+378', 'currency' => 'EUR'],
            ['name' => 'Sao Tome and Principe', 'code' => 'STP', 'dial_code' => '+239', 'currency' => 'STN'],
            ['name' => 'Saudi Arabia', 'code' => 'SA', 'dial_code' => '+966', 'currency' => 'SAR'],
            ['name' => 'Senegal', 'code' => 'SEN', 'dial_code' => '+221', 'currency' => 'XOF'],
            ['name' => 'Serbia', 'code' => 'SRB', 'dial_code' => '+381', 'currency' => 'RSD'],
            ['name' => 'Seychelles', 'code' => 'SYC', 'dial_code' => '+248', 'currency' => 'SCR'],
            ['name' => 'Sierra Leone', 'code' => 'SLE', 'dial_code' => '+232', 'currency' => 'SLL'],
            ['name' => 'Singapore', 'code' => 'SGP', 'dial_code' => '+65', 'currency' => 'SGD'],
            ['name' => 'Sint Maarten', 'code' => 'SXM', 'dial_code' => '+1721', 'currency' => 'ANG'],
            ['name' => 'Slovakia', 'code' => 'SVK', 'dial_code' => '+421', 'currency' => 'EUR'],
            ['name' => 'Slovenia', 'code' => 'SVN', 'dial_code' => '+386', 'currency' => 'EUR'],
            ['name' => 'Solomon Islands', 'code' => 'SLB', 'dial_code' => '+677', 'currency' => 'SBD'],
            ['name' => 'Somalia', 'code' => 'SOM', 'dial_code' => '+252', 'currency' => 'SOS'],
            ['name' => 'South Africa', 'code' => 'ZAF', 'dial_code' => '+27', 'currency' => 'ZAR'],
            ['name' => 'Spain', 'code' => 'ESP', 'dial_code' => '+34', 'currency' => 'EUR'],
            ['name' => 'Sri Lanka', 'code' => 'LKA', 'dial_code' => '+94', 'currency' => 'LKR'],
            ['name' => 'South Sudan', 'code' => 'SSD', 'dial_code' => '+211', 'currency' => 'SSP'],
            ['name' => 'Sudan', 'code' => 'SDN', 'dial_code' => '+249', 'currency' => 'SDG'],
            ['name' => 'Suriname', 'code' => 'SUR', 'dial_code' => '+597', 'currency' => 'SRD'],
            ['name' => 'Sweden', 'code' => 'SWE', 'dial_code' => '+46', 'currency' => 'SEK'],
            ['name' => 'Switzerland', 'code' => 'CHE', 'dial_code' => '+41', 'currency' => 'CHF'],
            ['name' => 'Syria', 'code' => 'SYR', 'dial_code' => '+963', 'currency' => 'SYP'],

            // T
            ['name' => 'Taiwan', 'code' => 'TWN', 'dial_code' => '+886', 'currency' => 'TWD'],
            ['name' => 'Tajikistan', 'code' => 'TJK', 'dial_code' => '+992', 'currency' => 'TJS'],
            ['name' => 'Tanzania', 'code' => 'TZA', 'dial_code' => '+255', 'currency' => 'TZS'],
            ['name' => 'Thailand', 'code' => 'THA', 'dial_code' => '+66', 'currency' => 'THB'],
            ['name' => 'Togo', 'code' => 'TGO', 'dial_code' => '+228', 'currency' => 'XOF'],
            ['name' => 'Tokelau', 'code' => 'TKL', 'dial_code' => '+690', 'currency' => 'NZD'],
            ['name' => 'Tonga', 'code' => 'TON', 'dial_code' => '+676', 'currency' => 'TOP'],
            ['name' => 'Trinidad and Tobago', 'code' => 'TTO', 'dial_code' => '+1868', 'currency' => 'TTD'],
            ['name' => 'Tunisia', 'code' => 'TUN', 'dial_code' => '+216', 'currency' => 'TND'],
            ['name' => 'Turkey', 'code' => 'TUR', 'dial_code' => '+90', 'currency' => 'TRY'],
            ['name' => 'Turkmenistan', 'code' => 'TKM', 'dial_code' => '+993', 'currency' => 'TMT'],
            ['name' => 'Tuvalu', 'code' => 'TUV', 'dial_code' => '+688', 'currency' => 'AUD'],
            ['name' => 'Turks and Caicos Islands', 'code' => 'TCA', 'dial_code' => '+1649', 'currency' => 'USD'],

            // U
            ['name' => 'Uganda', 'code' => 'UGA', 'dial_code' => '+256', 'currency' => 'UGX'],
            ['name' => 'Ukraine', 'code' => 'UKR', 'dial_code' => '+380', 'currency' => 'UAH'],
            ['name' => 'United Arab Emirates', 'code' => 'ARE', 'dial_code' => '+971', 'currency' => 'AED'],
            ['name' => 'United Kingdom', 'code' => 'GBR', 'dial_code' => '+44', 'currency' => 'GBP'],
            ['name' => 'United States', 'code' => 'USA', 'dial_code' => '+1', 'currency' => 'USD'],
            ['name' => 'United States Virgin Islands', 'code' => 'VIR', 'dial_code' => '+1340', 'currency' => 'USD'],
            ['name' => 'Uruguay', 'code' => 'URY', 'dial_code' => '+598', 'currency' => 'UYU'],
            ['name' => 'Uzbekistan', 'code' => 'UZB', 'dial_code' => '+998', 'currency' => 'UZS'],

            // V
            ['name' => 'Vanuatu', 'code' => 'VUT', 'dial_code' => '+678', 'currency' => 'VUV'],
            ['name' => 'Vatican City', 'code' => 'VAT', 'dial_code' => '+379', 'currency' => 'EUR'],
            ['name' => 'Venezuela', 'code' => 'VEN', 'dial_code' => '+58', 'currency' => 'VES'],
            ['name' => 'Vietnam', 'code' => 'VNM', 'dial_code' => '+84', 'currency' => 'VND'],

            // W
            ['name' => 'Wallis and Futuna', 'code' => 'WLF', 'dial_code' => '+681', 'currency' => 'XPF'],
            ['name' => 'West Bank', 'code' => 'PSE', 'dial_code' => '+970', 'currency' => 'ILS'],
            ['name' => 'Western Sahara', 'code' => 'ESH', 'dial_code' => '+212', 'currency' => 'MAD'],

            // Y
            ['name' => 'Yemen', 'code' => 'YEM', 'dial_code' => '+967', 'currency' => 'YER'],

            // Z
            ['name' => 'Zambia', 'code' => 'ZMB', 'dial_code' => '+260', 'currency' => 'ZMW'],
            ['name' => 'Zimbabwe', 'code' => 'ZWE', 'dial_code' => '+263', 'currency' => 'ZWL'],
        ];

        DB::table('countries')->insert($countries);
    }
}