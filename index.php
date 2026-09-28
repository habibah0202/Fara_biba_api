<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

const FILE_DATA = __DIR__ . '/data/nilai.json';

function kirim(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function bacaData(): array
{
    if (!file_exists(FILE_DATA)) {
        return [];
    }

    $isi = file_get_contents(FILE_DATA);

    return json_decode($isi, true) ?? [];
}

function simpanData(array $data): void
{
    file_put_contents(
        FILE_DATA,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

function tentukanKeterangan(float $nilai): string
{
    return $nilai >= 60 ? 'Lulus' : 'Tidak Lulus';
}

function ambilInput(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || $raw === '') {
        return is_array($_POST) ? $_POST : [];
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    parse_str($raw, $data);

    return is_array($data) ? $data : [];
}

function normalisasiPath(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

    if ($path === '') {
        return '/';
    }

    return rtrim($path, '/');
}

$method = $_SERVER['REQUEST_METHOD'];
$path = normalisasiPath();

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/*
|--------------------------------------------------------------------------
| GET /api/nilai
|--------------------------------------------------------------------------
*/
if ($method === 'GET' && preg_match('#/api/nilai$#', $path)) {

    $data = bacaData();

    kirim(200, [
        'status' => 'success',
        'total' => count($data),
        'data' => $data
    ]);
}

/*
|--------------------------------------------------------------------------
| POST /api/nilai
|--------------------------------------------------------------------------
*/
if ($method === 'POST' && preg_match('#/api/nilai$#', $path)) {

    $input = ambilInput();

    if (!is_array($input)) {
        kirim(400, [
            'status' => 'error',
            'pesan' => 'Body harus berupa JSON atau form-data yang valid'
        ]);
    }

    $nama = trim($input['nama'] ?? '');
    $mk = trim($input['mata_kuliah'] ?? '');
    $nilai = $input['nilai'] ?? null;

    $error = [];

    if ($nama === '') {
        $error[] = 'nama wajib diisi';
    }

    if ($mk === '') {
        $error[] = 'mata_kuliah wajib diisi';
    }

    if (!is_numeric($nilai) || $nilai < 0 || $nilai > 100) {
        $error[] = 'nilai harus angka 0 sampai 100';
    }

    if ($error) {
        kirim(400, [
            'status' => 'error',
            'pesan' => 'Data tidak valid',
            'detail' => $error
        ]);
    }

    $data = bacaData();

    $idBaru = $data
        ? max(array_column($data, 'id')) + 1
        : 1;

    $baru = [
        'id' => $idBaru,
        'nama' => $nama,
        'mata_kuliah' => $mk,
        'nilai' => $nilai + 0,
        'keterangan' => tentukanKeterangan((float) $nilai)
    ];

    $data[] = $baru;

    simpanData($data);

    kirim(201, [
        'status' => 'success',
        'pesan' => 'Data berhasil disimpan',
        'data' => $baru
    ]);
}

/*
|--------------------------------------------------------------------------
| PUT dan PATCH /api/nilai/{id}
|--------------------------------------------------------------------------
*/
if (
    ($method === 'PUT' || $method === 'PATCH')
    && preg_match('#/api/nilai/([0-9]+)$#', $path, $match)
) {

    $id = (int) $match[1];

    $data = bacaData();

    $index = null;

    foreach ($data as $key => $item) {
        if ((int) $item['id'] === $id) {
            $index = $key;
            break;
        }
    }

    if ($index === null) {
        kirim(404, [
            'status' => 'error',
            'pesan' => 'Data dengan ID tersebut tidak ditemukan'
        ]);
    }

    $input = ambilInput();

    if (!is_array($input)) {
        kirim(400, [
            'status' => 'error',
            'pesan' => 'Body harus berupa JSON atau form-data yang valid'
        ]);
    }

    /*
    |--------------------------------------------------------------
    | PUT = mengganti data secara keseluruhan
    |--------------------------------------------------------------
    */
    if ($method === 'PUT') {

        $nama = trim($input['nama'] ?? '');
        $mk = trim($input['mata_kuliah'] ?? '');
        $nilai = $input['nilai'] ?? null;

        if (
            $nama === '' ||
            $mk === '' ||
            !is_numeric($nilai) ||
            $nilai < 0 ||
            $nilai > 100
        ) {
            kirim(400, [
                'status' => 'error',
                'pesan' => 'Untuk PUT, nama, mata_kuliah, dan nilai wajib diisi dengan benar'
            ]);
        }

        $data[$index] = [
            'id' => $id,
            'nama' => $nama,
            'mata_kuliah' => $mk,
            'nilai' => $nilai + 0,
            'keterangan' => tentukanKeterangan((float) $nilai)
        ];
    }

    /*
    |--------------------------------------------------------------
    | PATCH = mengubah sebagian data
    |--------------------------------------------------------------
    */
    if ($method === 'PATCH') {

        if (isset($input['nama'])) {
            $data[$index]['nama'] = trim($input['nama']);
        }

        if (isset($input['mata_kuliah'])) {
            $data[$index]['mata_kuliah'] = trim($input['mata_kuliah']);
        }

        if (isset($input['nilai'])) {

            if (
                !is_numeric($input['nilai']) ||
                $input['nilai'] < 0 ||
                $input['nilai'] > 100
            ) {
                kirim(400, [
                    'status' => 'error',
                    'pesan' => 'nilai harus angka 0 sampai 100'
                ]);
            }

            $data[$index]['nilai'] = $input['nilai'] + 0;

            $data[$index]['keterangan'] =
                tentukanKeterangan((float) $input['nilai']);
        }
    }

    simpanData($data);

    kirim(200, [
        'status' => 'success',
        'pesan' => 'Data berhasil diperbarui',
        'data' => $data[$index]
    ]);
}

/*
|--------------------------------------------------------------------------
| DELETE /api/nilai/{id}
|--------------------------------------------------------------------------
*/
if (
    $method === 'DELETE'
    && preg_match('#/api/nilai/([0-9]+)$#', $path, $match)
) {

    $id = (int) $match[1];

    $data = bacaData();

    $dataBaru = [];
    $ditemukan = false;

    foreach ($data as $item) {

        if ((int) $item['id'] === $id) {
            $ditemukan = true;
            continue;
        }

        $dataBaru[] = $item;
    }

    if (!$ditemukan) {
        kirim(404, [
            'status' => 'error',
            'pesan' => 'Data dengan ID tersebut tidak ditemukan'
        ]);
    }

    simpanData($dataBaru);

    kirim(200, [
        'status' => 'success',
        'pesan' => 'Data berhasil dihapus'
    ]);
}

/*
|--------------------------------------------------------------------------
| Endpoint / method tidak ditemukan
|--------------------------------------------------------------------------
*/

kirim(404, [
    'status' => 'error',
    'pesan' => 'Endpoint tidak ditemukan'
]);