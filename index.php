#!/usr/bin/php
<?php
require 'vendor/autoload.php';

function parseTags(string $tags) {
    return preg_split("/<|>/", $tags, -1, PREG_SPLIT_NO_EMPTY);
}

function computePosts() {
    $posts = array();
    $posts_answer = array();
    //$xml = simplexml_load_file('./beer.stackexchange.com/Posts.xml');
    $xml = simplexml_load_file('./askubuntu.stackexchange.com/Posts.xml');

    foreach($xml->row as $row) {
        $attribute = $row->attributes();
        if ($attribute->PostTypeId == 1) {

            $post = [
                "AcceptedAnswerId" => (string) $attribute->AcceptedAnswerId,
                "CreationDate" => (string) $attribute->CreationDate,
                "Score" => (int) $attribute->Score,
                "ViewCount" => (int) $attribute->ViewCount,
                "Body" => (string) $attribute->Body,
                "Title" => (string) $attribute->Title,
                "OwnerUserId" => (int) $attribute->OwnerUserId,
                "Tags" => parseTags((string) $attribute->Tags),
                "AnswerCount" => (int) $attribute->AnswerCount,
                "CommentCount" => (int) $attribute->CommentCount,
                "Answer" => array()
            ];

            $posts[(int) $attribute->Id] = $post;
        } else if ($attribute->PostTypeId == 2) {
            $answer = [
                "Id" => (int) $attribute->Id,
                "ParentId" => (int) $attribute->ParentId,
                "CreationDate" => (string) $attribute->CreationDate,
                "Score" => (int) $attribute->Score, "Body" => (string) $attribute->Body,
                "OwnerUserId" => (int) $attribute->OwnerUserId,
                "CommentCount" => (int) $attribute->CommentCount
            ];

            array_push($posts_answer, $answer);
        } else {
            #for latter
        }
    }

    foreach($posts_answer as $answer) {
        $id = $answer["ParentId"];
        unset($answer["ParentId"]);

        $post = $posts[$id];
        array_push($post["Answer"], $answer);

        $posts[$id] = $post;
    }

    return $posts;
}

function indexPosts($esClient, $posts) {
    $params = ['body' => []];
    $i = 0;
    foreach($posts as $id => $post) {
        $params['body'][] = [
            'index' => [
                '_index' => 'beer',
                '_type' => 'post',
                '_id' => $id
            ]
        ];

        $params['body'][] = $post;

        if ($i % 100 == 0) {
            print_r($esClient->bulk($params)['errors']);

            $params = ['body' => []];
        }
        $i++;
    }

    if (!empty($params['body'])) {
        print_r('Error: ' . $esClient->bulk($params)['errors']);
    }
}

function getElasticSearchClient() {
    return Elasticsearch\ClientBuilder::create()
        ->setHosts(['localhost:9200'])
        ->build();
}

function createBeerIndex($esClient) {
    $esClient->indices()->delete([
        'index' => 'beer',
        'client' => ['ignore' => 404]
    ]);

    $postAnalazer = [];

    $postMapping = [
        'index' => 'beer',
        'body' => [
            'settings' => [
                'analysis' => json_decode(file_get_contents('index_settings/beer.json'))
            ],
            'mappings' => [
                'post' => json_decode(file_get_contents('mapping/post.json'))
            ]
        ]
    ];

    $response = $esClient->indices()->create($postMapping);
    print_r($response);
}

function indexBeer($esClient) {
    createBeerIndex($esClient);
    $posts = computePosts();
    print_r(count($posts));
    indexPosts($esClient, $posts);
}

$esClient = getElasticSearchClient();
indexBeer($esClient);