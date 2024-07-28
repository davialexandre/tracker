<?php

$pdo = new PDO(getenv('DB_DSN'));

$pdo->exec(<<<SQL
create table if not exists location
(
    id integer not null
        constraint id
            primary key autoincrement,
    acc integer not null,
    alt integer not null,
    lat REAL not null,
    lon REAL not null,
    vac integer not null,
    vel integer not null,
    tst integer not null,
    received_at integer
);
SQL);
