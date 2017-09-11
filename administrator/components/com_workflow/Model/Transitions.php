<?php
/**
 * Items Model for a Prove Component.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_prove
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       4.0
 */
namespace Joomla\Component\Workflow\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Model\ListModel;
use Joomla\Component\Workflow\Administrator\Helper\WorkflowHelper;

/**
 * Model class for items
 *
 * @since  4.0
 */
class Transitions extends ListModel
{
	/**
	 * Constructor.
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 *
	 * @see     JController
	 * @since   1.6
	 */
	public function __construct($config = array())
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = array(
				'id',
				'published',
				'title',
				'from_state',
				'to_state'
			);
		}

		parent::__construct($config);
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * This method should only be called once per instantiation and is designed
	 * to be called on the first call to the getState() method unless the model
	 * configuration flag to ignore the request is set.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @param   string  $ordering   An optional ordering field.
	 * @param   string  $direction  An optional direction (asc|desc).
	 *
	 * @return  void
	 *
	 * @since   4.0
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		$app = Factory::getApplication();
		$workflowID = $app->getUserStateFromRequest($this->context . '.filter.workflow_id', 'workflow_id', 1, 'int');
		$extension = $app->getUserStateFromRequest($this->context . '.filter.extension', 'extension', 'com_content', 'cmd');
		
		if ($workflowID)
		{
			$table = $this->getTable('Workflow', 'Administrator');
			
			if (!empty($table->load($workflowID)))
			{
				$this->setState('active_workflow', $table->title);
			}
		}
		
		$this->setState('filter.workflow_id', $workflowID);
		$this->setState('filter.extension', $extension);

		parent::populateState($ordering, $direction);

		// TODO: Change the autogenerated stub
	}


	/**
	 * Method to get a table object, load it if necessary.
	 *
	 * @param   string  $type    The table name. Optional.
	 * @param   string  $prefix  The class prefix. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  \Joomla\CMS\Table\Table  A JTable object
	 *
	 * @since   4.0
	 */
	public function getTable($type = 'Transition', $prefix = 'Administrator', $config = array())
	{
		return parent::getTable($type, $prefix, $config);
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  string  The query to database.
	 *
	 * @since   4.0
	 */
	public function getListQuery()
	{
		$db = $this->getDbo();

		$query = parent::getListQuery();

		$select = $db->quoteName(
			array(
			'transition.id',
			'transition.title',
			'transition.published',
		)
		);
		$select[] = $db->qn('f_state.title', 'from_state');
		$select[] = $db->qn('t_state.title', 'to_state');
		$joinTo = $db->qn('#__workflow_states', 't_state') .
			' ON ' . $db->qn('t_state.id') . ' = ' . $db->qn('transition.to_state_id');

		$query
			->select($select)
			->from($db->qn('#__workflow_transitions', 'transition'))
			->leftJoin(
				$db->qn('#__workflow_states', 'f_state') . ' ON ' . $db->qn('f_state.id') . ' = ' . $db->qn('transition.from_state_id')
			)
			->leftJoin($joinTo);

		// Filter by extension
		if ($workflowID = (int) $this->getState('filter.workflow_id'))
		{
			$query->where($db->qn('transition.workflow_id') . ' = ' . $workflowID);
		}

		$status = $this->getState('filter.published');

		// Filter by condition
		if (is_numeric($status))
		{
			$query->where($db->qn('transition.published') . ' = ' . (int) $status);
		}
		elseif ($status == '')
		{
			$query->where($db->qn('transition.published') . " IN ('0', '1')");
		}

		// Filter by column from_state_id
		if ($fromState = $this->getState('filter.from_state'))
		{
			$query->where($db->qn('from_state_id') . ' = ' . (int) $fromState);
		}

		// Filter by column from_state_id
		if ($toState = $this->getState('filter.to_state'))
		{
			$query->where($db->qn('to_state_id') . ' = ' . (int) $toState);
		}

		// Filter by search in title
		$search = $this->getState('filter.search');

		if (!empty($search))
		{
			$search = $db->quote('%' . str_replace(' ', '%', $db->escape(trim($search), true) . '%'));
			$query->where('(' . $db->qn('title') . ' LIKE ' . $search . ' OR ' . $db->qn('description') . ' LIKE ' . $search . ')');
		}

		// Add the list ordering clause.
		$orderCol	= $this->state->get('list.ordering', 'id');
		$orderDirn 	= strtolower($this->state->get('list.direction', 'asc'));

		$query->order($db->quoteName($orderCol) . ' ' . $db->escape($orderDirn == 'desc' ? 'DESC' : 'ASC'));

		return $query;
	}

	/**
	 * Get the filter form
	 *
	 * @param   array    $data      data
	 * @param   boolean  $loadData  load current data
	 *
	 * @return  \JForm|boolean  The \JForm object or false on error
	 *
	 * @since   3.2
	 */
	public function getFilterForm($data = array(), $loadData = true)
	{
		$form = parent::getFilterForm($data, $loadData);
		$id = (int) $this->getState('filter.workflow_id');

		$sqlStatesFrom = WorkflowHelper::getStatesSQL('from_state', $id);
		$sqlStatesTo = WorkflowHelper::getStatesSQL('to_state', $id);

		if ($form)
		{
			$form->setFieldAttribute('from_state', 'query', $sqlStatesFrom, 'filter');
			$form->setFieldAttribute('to_state', 'query', $sqlStatesTo, 'filter');
		}

		return $form;
	}
}
