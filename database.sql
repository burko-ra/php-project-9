CREATE TABLE urls (
    id bigint PRIMARY GENERATED ALWAYS AS IDENTITY,
    name varchar(255) UNIQUE NOT NULL,
    created_at timestamp 
);