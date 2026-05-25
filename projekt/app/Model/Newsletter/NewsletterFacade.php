<?php

namespace App\Model\Newsletter;

use Nette\Database\Explorer;
use Nette\Mail\Mailer;
use Nette\Mail\Message;

final class NewsletterFacade
{
    public function __construct(
        private Explorer $database,
        private Mailer $mailer,
    ) {}

    // Prihlas email k odberu. Hazi vyjimku pokud email uz existuje
    public function subscribe(string $email): void
    {
        if ($this->isSubscribed($email)) {
            throw new \RuntimeException('Tento email je již přihlášen k odběru.');
        }
        $this->database->table('newsletter_subscribers')->insert([
            'email'      => $email,
            'created_at' => new \DateTimeImmutable(),
        ]);
    }

    public function unsubscribe(string $email): void
    {
        $this->database->table('newsletter_subscribers')->where('email', $email)->delete();
    }

    public function isSubscribed(string $email): bool
    {
        return (bool) $this->database->table('newsletter_subscribers')->where('email', $email)->fetch();
    }

    public function getSubscriberCount(): int
    {
        return $this->database->table('newsletter_subscribers')->count('*');
    }

    // Odesle newsletter o novem prispevku vsem odberatelum
    // Vrati pocet odeslanych emailu
    public function sendNewPost(int $postId, string $baseUrl): int
    {
        $post = $this->database->table('posts')->get($postId);
        if (!$post) {
            throw new \RuntimeException('Příspěvek nenalezen.');
        }

        $subscribers = $this->database->table('newsletter_subscribers')->fetchAll();
        $sent = 0;

        foreach ($subscribers as $sub) {
            $mail = new Message();
            $mail->setFrom('newsletter@vojensky-blog.cz', 'Vojenská Technika');
            $mail->addTo($sub->email);
            $mail->setSubject('Nový článek: ' . $post->title);
            $mail->setHtmlBody(
                '<h2>' . htmlspecialchars($post->title) . '</h2>' .
                '<p>' . htmlspecialchars(mb_substr(strip_tags($post->content), 0, 300)) . '…</p>' .
                '<p><a href="' . $baseUrl . '/post/show/' . $post->id . '">Číst celý článek →</a></p>' .
                '<hr><small>Odhlásit se: <a href="' . $baseUrl . '/newsletter/unsubscribe?email=' .
                urlencode($sub->email) . '">' . $sub->email . '</a></small>'
            );

            try {
                $this->mailer->send($mail);
                $sent++;
            } catch (\Exception) {
                // jeden neuspesny email nezastavi odesilani ostatnim
            }
        }

        return $sent;
    }
}
