<?php
// Small helpers for responses and request parsing
function jsonResponse($response, $data, $status = 200)
{
    $response->getBody()->write(json_encode($data));
    return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
}

function getJsonInput()
{
    $input = json_decode(file_get_contents('php://input'), true);
    return $input ?: [];
}
