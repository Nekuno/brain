<?php

namespace ApiConsumer\LinkProcessor\Processor\SpotifyProcessor;

use ApiConsumer\Exception\CannotProcessException;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\SynonymousParameters;
use ApiConsumer\LinkProcessor\UrlParser\YoutubeUrlParser;
use Model\Audio;

class SpotifyTrackProcessor extends AbstractSpotifyProcessor
{

    function requestItem(PreprocessedLink $preprocessedLink)
    {
        $id = $this->getItemId($preprocessedLink->getCanonical());
        $preprocessedLink->setResourceItemId($id);

        $track = $this->resourceOwner->requestTrack($id);

        if (!(isset($track['album']) && isset($track['name']) && isset($track['artists']))) {
            throw new CannotProcessException($preprocessedLink->getCanonical());
        }

        $album = $this->resourceOwner->requestAlbum($track['album']['id']);

        return array('track' => $track, 'album' => $album);
    }

    function hydrateLink(PreprocessedLink $preprocessedLink, array $data)
    {
        $link = $preprocessedLink->getLink();

        $track = $data['track'];

        $artistList = $this->buildArtistList($track);

        $link->setTitle($track['name']);
        $link->setDescription($track['album']['name'] . ' : ' . implode(', ', $artistList));
        $link->setThumbnail(isset($track['album']['images'][1]['url']) ? $track['album']['images'][1]['url'] : null);

        $link = Audio::buildFromLink($link);
        $link->setEmbedType('spotify');
        $link->setEmbedId($track['uri']);

        $preprocessedLink->setLink($link);
    }

    function addTags(PreprocessedLink $preprocessedLink, array $data)
    {
        $link = $preprocessedLink->getLink();

        $album = $data['album'];
        if (isset($album['genres'])) {
            foreach ($album['genres'] as $genre) {
                $link->addTag($this->buildMusicalGenreTag($genre));
            }
        }

        $track = $data['track'];
        foreach ($track['artists'] as $artist) {
            $link->addTag($this->buildArtistTag($artist));
        }

        $link->addTag($this->buildAlbumTag($album));
        $link->addTag($this->buildSongTag($track));
    }

    function getSynonymousParameters(PreprocessedLink $preprocessedLink, array $data)
    {
        $track = $data['track'];
        $artistList = $this->buildArtistList($track);
        $song = $track['name'];

        $queryString = implode(', ', $artistList) . ' ' . $song;

        $synonymousParameters = new SynonymousParameters();
        $synonymousParameters->setQuantity(3);
        $synonymousParameters->setQuery($queryString);
        $synonymousParameters->setComparison($preprocessedLink->getLink()->getTitle());
        $synonymousParameters->setType(YoutubeUrlParser::VIDEO_URL);

        $preprocessedLink->setSynonymousParameters($synonymousParameters);
    }
}