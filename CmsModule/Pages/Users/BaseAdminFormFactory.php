<?php

/**
 * This file is part of the Venne:CMS (https://github.com/Venne)
 *
 * Copyright (c) 2011, 2012 Josef Kříž (http://www.josef-kriz.cz)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace CmsModule\Pages\Users;

use CmsModule\Content\Forms\ControlExtensions\ControlExtension;
use Doctrine\DBAL\DBALException;
use DoctrineModule\Forms\FormFactory;
use Venne\Forms\Form;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
abstract class BaseAdminFormFactory extends FormFactory
{

	/**
	 * @return array
	 */
	protected function getControlExtensions()
	{
		return array_merge(parent::getControlExtensions(), array(
			new ControlExtension,
		));
	}


	/**
	 * @param Form $form
	 */
	protected function configure(Form $form)
	{
		$user = $form->addOne('user');
		$group = $form->addGroup();
		$user->setCurrentGroup($group);
		$user->addCheckbox('published', 'Enable');
		$user->addText('email', 'E-mail')
			->addRule(Form::EMAIL, 'Enter email');
		$user->addText('name', 'Name');
		$user->addTextArea('notation', 'Notation', 40, 4)
			->getControlPrototype()->attrs['class'] = 'input-block-level';

		$route = $user->addOne('route');
		$route->setCurrentGroup($group);
		$route->addFileEntityInput('photo', 'Avatar');

		$user->setCurrentGroup($form->addGroup('Block by key'));
		$user->addCheckbox('key_new', 'Enable')->addCondition($form::EQUAL, TRUE)->toggle('setKey');
		$user->setCurrentGroup($form->addGroup()->setOption('id', 'setKey'));
		$user->addText('key', 'Authentization key')->setOption('description', 'If is set user cannot log in.');

		$user->setCurrentGroup($form->addGroup('Password'));
		$user->addCheckbox('password_new', 'Change password')->addCondition($form::EQUAL, TRUE)->toggle('setPasswd');
		$user->setCurrentGroup($form->addGroup()->setOption('id', 'setPasswd'));
		$user->addPassword('password', 'Password')
			->setOption('description', 'minimal length is 5 char')
			->addConditionOn($user['password_new'], Form::FILLED)
			->addRule(Form::FILLED, 'Enter password')
			->addRule(Form::MIN_LENGTH, 'Password is short', 5);
		$user->addPassword('password_confirm', 'Confirm password')
			->addConditionOn($user['password_new'], Form::FILLED)
			->addRule(Form::EQUAL, 'Invalid re password', $user['password']);

		$user->setCurrentGroup($form->addGroup('Next informations'));
		$user->addManyToMany('roleEntities', 'Roles');
	}


	public function handleCatchError(Form $form, $e)
	{
		if ($e instanceof DBALException && $e->getCode() == '23000') {
			$form->addError('User is not unique');
			return TRUE;
		}
	}


	public function handleAttached(Form $form)
	{
		$form->setCurrentGroup();
		$form->addSaveButton('Save');

		if ($form->isSubmitted()) {
			if (!$form['user']['password_new']->value) {
				unset($form['user']['password']);
			}
		}
	}


	public function handleLoad(Form $form)
	{
		if ($form->data->user->key) {
			$form['user']['key_new']->value = TRUE;
		}
	}


	public function handleSave(Form $form)
	{
		if ($form['user']['password_new']->value) {
			$form->data->user->setPassword($form['user']['password']->value);
		}
		if (!$form['user']['key_new']->value) {
			$form->data->user->setKey(NULL);
		}

		parent::handleSave($form);
	}
}
