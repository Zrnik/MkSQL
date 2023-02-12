<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Zrnik\MkSQL;

use Nette\Utils\Strings;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use function strtolower;

class Utils
{
    /**
     * List of default allowed characters.
     *
     * @var string[]
     */
    private static array $_defaultAllowedCharacters = [
        'A', 'a', 'B', 'b', 'C', 'c', 'D', 'd', 'E', 'e', 'F', 'f', 'G', 'g', 'H', 'h', 'I', 'i', 'J', 'j',
        'K', 'k', 'L', 'l', 'M', 'm', 'N', 'n', 'O', 'o', 'P', 'p', 'Q', 'q', 'R', 'r', 'S', 's', 'T', 't',
        'U', 'u', 'V', 'v', 'W', 'w', 'X', 'x', 'Y', 'y', 'Z', 'z',
        '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '_'
    ];
    /**
     * List of banned words in comments & default values!
     * (As we are using them to parse SHOW CREATE TABLE results)
     *
     * @var string[]
     */
    private static array $_Forbidden = [
        'NOT NULL', 'DEFAULT', 'CREATE TABLE', 'CONSTRAINT',
        'REFERENCES', 'CREATE UNIQUE INDEX', 'PRIMARY KEY'
    ];

    /**
     * YAGNI but testing for peace of mind
     * @param string $name
     * @return string
     * @throws InvalidArgumentException
     */
    public static function internalTestCommentsError(string $name): string
    {
        return self::confirmName($name, ['/', '*', '-']);
    }

    /**
     * This will confirm if the name is good for use in SQL query.
     * We are (against all rules) concatenating strings when making queries.
     *
     * @param string $name
     * @param string[] $AdditionalAllowed
     * @return string
     * @throws InvalidArgumentException
     */
    private static function confirmName(string $name, array $AdditionalAllowed = []): string
    {
        // Comments are not allowed obviously :)
        if (Strings::contains($name, '--') || Strings::contains($name, '/*') || Strings::contains($name, '*/')) {
            {
                throw new InvalidArgumentException('Comment found in SQL query!');
            }
        }

        // Remove allowed characters, if the string isn't empty, it contains invalid characters!
        $Allowed = array_merge(self::$_defaultAllowedCharacters, $AdditionalAllowed);

        if (str_replace($Allowed, '', $name) !== '') {
            {
                throw new InvalidArgumentException("Argument '" . $name . "' contains invalid characters!");
            }
        }

        // "It's a kind of fall-trough" - Freddie
        return Strings::toAscii($name);
    }

    /**
     * Table name only allows a-z, A-Z, 0-9 and underscore
     *
     * @param string $name
     * @return string
     * @throws InvalidArgumentException
     */
    public static function confirmTableName(string $name): string
    {
        return self::confirmName($name);
    }

    /**
     * Column name only allows a-z, A-Z, 0-9 and underscore
     *
     * @param string $name
     * @return string
     * @throws InvalidArgumentException
     */
    public static function confirmColumnName(string $name): string
    {
        return self::confirmName($name);
    }

    /**
     * Table name only allows a-z, A-Z, 0-9, underscore, parentheses and comma
     *
     * @param string $type
     * @return string
     * @throws InvalidArgumentException
     */
    public static function confirmType(string $type): string
    {
        return self::confirmName(str_replace(' ', '', $type), ['(', ')', ',']);
    }

    /**
     * If the key is too long, we use md5 to make it shorter and trim it if required.
     *
     * @param string $keyName
     * @param int $maxLen
     * @return string
     * @throws InvalidArgumentException
     */
    public static function confirmKeyName(string $keyName, int $maxLen = 64): string
    {
        if (Strings::length($keyName) > $maxLen) {
            {
                return substr(md5($keyName), 0, min(32, $maxLen));
            }
        }
        return self::confirmName($keyName);
    }

    /**
     * It actually needs a dot... and only one!
     *
     * @param string $keyTarget
     * @return string
     * @throws InvalidArgumentException
     */
    public static function confirmForeignKeyTarget(string $keyTarget): string
    {
        $keyTarget = self::confirmName($keyTarget, ['.']);

        if (!Strings::contains($keyTarget, '.')) {
            throw new InvalidArgumentException("Invalid foreign key target '" . $keyTarget . "'. Dot is missing.");
        }

        if (substr_count($keyTarget, '.') > 1) {
            throw new InvalidArgumentException("Invalid foreign key target '" . $keyTarget . "'. Too many dots.");
        }

        return $keyTarget;
    }

    /**
     * Comment only allows a-z, A-Z, 0-9, underscore, dot, comma and a space.
     * We like to form sentences, but no apostrophe or quotes, sorry...
     *
     * Can be NULL!
     *
     * @param float|bool|int|string|null $name
     * @return float|bool|int|string|null
     * @throws InvalidArgumentException
     */
    public static function confirmComment(float|bool|int|string|null $name): float|bool|int|string|null
    {
        if ($name === null) {
            return null;
        }

        return self::checkForbiddenWords(self::confirmName((string)$name, [',', '.', ' ']));
    }

    /**
     * Fall through checking for banned words in string.
     * Case Insensitive
     *
     * @param float|bool|int|string|null $text
     * @return float|bool|int|string|null
     * @throws InvalidArgumentException
     */
    public static function checkForbiddenWords(float|bool|int|string|null $text): float|bool|int|string|null
    {
        if ($text !== null) {
            foreach (self::$_Forbidden as $ForbiddenWord) {
                if (Strings::contains(
                    strtolower((string)$text),
                    strtolower($ForbiddenWord)
                )) {
                    throw new InvalidArgumentException("Forbidden word '" . strtolower($ForbiddenWord) . "' encountered!");
                }
            }
        }

        return $text;
    }


}
