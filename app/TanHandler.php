<?php


namespace App;


use Fhp\BaseAction;
use Fhp\FinTsNew;
use Symfony\Component\HttpFoundation\Session\Session;
use Twig\Environment;

class TanHandler
{
    public function __construct(
        callable $create_action_lambda, string $action_id, Session $session, Environment $twig, FinTsNew $fin_ts, \App\Step $current_step
    )
    {
        $this->create_action_lambda = $create_action_lambda;
        $this->action_id            = $action_id;
        $this->session              = $session;
        $this->twig                 = $twig;
        $this->fin_ts               = $fin_ts;
        $this->current_step         = $current_step;
        $this->create_or_continue_action();
    }

    private function create_or_continue_action(): void
    {
        if ($this->session->has($this->action_id)) {
            echo "<br>ResumingAction<br>"; //TODO debug
            $this->action = unserialize($this->session->get($this->action_id));
            $this->session->remove($this->action_id);
            $this->fin_ts->submitTan($this->action, $this->session->get('tan'));
        } else {
            echo "<br>New Action<br>"; //TODO debug
            $this->action = ($this->create_action_lambda)();
            echo "<br>Needs login:" . (int)$this->needs_tan() . "<br>"; //TODO debug
        }
    }

    public function needs_tan(): bool
    {
        return $this->action->needsTan();
    }

    public function pose_and_render_tan_challenge(): void
    {
        assert($this->needs_tan());
        $tanRequest = $this->action->getTanRequest();
        if ($tanRequest->getChallengeHhdUc()) {
            try {
                $challengeImage    = new \Fhp\Model\TanRequestChallengeImage(
                    $tanRequest->getChallengeHhdUc()
                );
                $challengeImageSrc =
                    'data:' . htmlspecialchars($challengeImage->getMimeType()) .
                    ';base64,' . base64_encode($challengeImage->getData());
            } catch (\RuntimeException $e) {
                $challengeImageSrc = null;
            }
        }
        echo $this->twig->render(
            'tan-challenge.twig',
            array(
                'next_step' => $this->current_step,
                'challenge' => $tanRequest->getChallenge(),
                'device' => $tanRequest->getTanMediumName(),
                'challenge_image_src' => $challengeImageSrc
            )
        );
        $this->session->set($this->action_id, serialize($this->action));
    }

    public function get_finished_action(): BaseAction
    {
        return $this->action;
    }

    private $action;
    private $create_action_lambda;
    private $action_id;
    private $session;
    private $twig;
    private $fin_ts;
    private $current_step;
}